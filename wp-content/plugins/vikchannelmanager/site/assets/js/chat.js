(function($, w) {
	'use strict';

	/**
	 * VCMChat class.
	 * Singleton used to handle a CHAT client.
	 */
	w['VCMChat'] = class VCMChat {

		/**
		 * Returns a new chat instance.
		 * 
		 * @param 	object 	data 	The environment options.
		 *
		 * @return 	VCMChat
		 */
		static getInstance(data) {
			if (VCMChat.instance === undefined || typeof data !== 'undefined') {
				VCMChat.instance = new VCMChat(data);
			}

			return VCMChat.instance;
		}

		/**
		 * Class constructor.
		 *
		 * @param 	object 	data 	The environment options.
		 */
		constructor(data) {
			this.data = data;

			if (this.data.environment === undefined || this.data.element === undefined || this.data.template === undefined) {
				throw 'Malformed data';
			}

			if (this.data.environment.threads === undefined) {
				this.data.environment.threads = [];
			} else if (this.data.environment.threads.length) {
				this.data.environment.activeThread = this.data.environment.threads[0].id;
			}

			for (var i = 0; i < this.data.environment.threads.length; i++) {
				var thread = this.data.environment.threads[i];

				// initialise thread
				this.initThread(thread);
			}

			this.data.environment.id          = this.data.environment.threads.length;
			this.data.environment.datetime 	  = new Date();
			this.data.environment.attachments = [];

			this.timers = [];
		}

		/**
		 * Initialises the specified thread.
		 * This method should be invoked before pushing
		 * a new thread within the list, so that it can support
		 * environment variables.
		 *
		 * @param 	object 	thread 	The thread to init.
		 *
		 * @return 	self
		 */
		initThread(thread) {
			// keep thread initial date time
			thread.initialDatetime = thread.last_updated;
			// keep thread initial messages length
			thread.messagesLength  = thread.messages.length;
			// reset notifications
			thread.notifications = 0;

			// iterate to count unread messages
			for (var i = 0; i < thread.messages.length; i++) {
				var msg = thread.messages[i];
				// make sure we are fetching a message sent by someone else
				// and that doesn't own a read datetime
				if (!this.isSender(msg) && !msg.read_dt) {
					// increase notifications
					thread.notifications++;
				}
			}

			return this;
		}

		/**
		 * Prepares the chat client to be up and running by bulding
		 * the threads list and initializing the last conversation made.
		 * The synchronization with the server is made here.
		 *
		 * @return 	self
		 */
		prepare() {
			var chat = this;

			if (this.isPrepared) {
				// do not execute again
				return this;
			}

			this.isPrepared = true;

			// register e-mail content parser
			this.attachContentParser('email', function(content) {
				// wrap any e-mail addresses within a "mailto" link
				content = content.replace(/[a-z0-9][a-z0-9._\-]{1,63}@(?:[a-z][a-z0-9\-]{1,62}\.?){1,3}\.[a-z][a-z0-9]{1,62}/gi, function(mail) {
					return '<a href="mailto:' + mail + '">' + mail + '</a>';
				});

				return content;
			});

			// register phone content parser
			this.attachContentParser('phone', function(content) {
				// wrap any potential phone numbers within a "tel" link
				content = content.replace(/(?:\+[\d]{1,5})?[\d][\d \-]{3,}[\d]/g, function(phone) {
					return '<a href="tel:' + phone + '">' + phone + '</a>';
				});

				return content;
			});

			// register URL content parser
			this.attachContentParser('url', function(content) {
				// wrap any plain URLs within a link
				content = content.replace(/https?:\/\/(www\.)?[a-zA-Z0-9@:%._\+~#=\-]{2,256}\.[a-z]{2,6}\b([a-zA-Z0-9@:%_\+.~#?&\/\/=\-]*)/gi, function(url) {
					return '<a href="' + url + '" target="_blank">' + url + '</a>';
				});

				return content;
			});

			this.renderInput()
				.startConversation()
				.buildThreads()
				.buildConversation()
				.readNotifications();

			// calculate interval duration between each sync (use at least 1000 ms)
			var syncDuration = Math.max(1000, (this.data.environment.options.syncTime || 10) * 1000);

			// sync threads when page loads
			chat.synchronizeThreads();

			// try to synchronize the threads to check if we have new messages to push
			this.timers.push(
				setInterval(function() {
					chat.synchronizeThreads();
				}, syncDuration)
			);

			/**
			 * We should run here an interval that re-build the date separators.
			 * For example, if we open the chat @ 23:57, the date separators should
			 * be changed after the midnight ("Today, 23:56" should become "Yesterday, 23:56").
			 *
			 * The date of the last message sent/received (reported on the threads list)
			 * should be updated too.
			 */
			this.timers.push(
				setInterval(function() {
					var now = new Date();

					// check if the date has changed since the last check
					if (!DateHelper.isSameDay(now, chat.data.environment.datetime)) {
						// update environment datetime
						chat.data.environment.datetime = now;

						// iterate all the separators
						$('.is-a-separator').each(function() {
							// get separator UTC date
							var dt  = $(this).attr('data-datetime');
							var utc = DateHelper.stringToDate(dt);
							
							// replace separator with new one
							$(this).replaceWith(chat.getDateSeparator(utc));
						});

						// re-build threads to update datetime too (don't need to re-sort them)
						chat.buildThreads();
					}
				}, 10000)
			);

			return this;
		}

		/**
		 * Builds the threads list.
		 * The the thread with the most recent message will be active by default.
		 *
		 * @param 	boolean  sort 	True to re-sort the threads within the list.
		 * 							Threads are always sorted by descending 
		 * 							datetime (most recent -> oldest).
		 *
		 * @return 	self
		 */
		buildThreads(sort) {
			var chat = this;
			var html = '';

			var gotNewThread = false;

			for (var i = 0; i < this.data.environment.threads.length; i++) {
				var thread = this.data.environment.threads[i];

				// update datetime if needed
				if (thread.messages.length) {
					var thread_dt = DateHelper.stringToDate(thread.last_updated);
					var mess_dt   = DateHelper.stringToDate(thread.messages[0].dt);

					if (thread_dt.getTime() < mess_dt.getTime()) {
						// update thread datetime
						thread.last_updated = thread.messages[0].dt;
					}
				}

				// fetch date time
				var dt_str   = '';
				var datetime = thread.last_updated;
				
				if (DateHelper.isToday(datetime)) {
					// current day: get formatted time
					dt_str = DateHelper.getFormattedTime(datetime);
				} else if (DateHelper.isYesterday(datetime)) {
					// previous day: use "yesterday"
					dt_str = Joomla.JText._(this.data.lang.yesterday);
				} else if ((dt_str = DateHelper.diff(datetime, new Date(), 'days')) < 7) {
					var tmp = new Date();
					tmp.setDate(tmp.getDate() - dt_str);
					dt_str = tmp.toLocaleDateString([], {weekday: 'long'});
				} else {
					// use formatted date
					dt_str = DateHelper.getFormattedDate(datetime);
				}

				var lookup = {
					recipient: 		thread.subject,
					datetime: 		dt_str,
					notifications: 	thread.notifications ? '<span>' + thread.notifications + '</span>' : '',
					// don't need to take a substring of the message because
					// the system already hides exceeding chars via CSS
					message: 		thread.messages.length ? thread.messages[0].content : '',
				};

				var tmpl = this.getTemplate('thread', lookup);

				// check whether we have a temporary thread within the list
				gotNewThread = gotNewThread || (thread.id + '').match(/^tmp/);

				// setup thread data
				var threadData = '';
				threadData += ' data-thread-id="' + thread.id + '"';
				threadData += ' data-thread-datetime="' + thread.last_updated + '"';
				threadData += ' data-thread-channel="' + thread.channel + '"';

				html += '<li class="thread-record' + (this.isThreadActive(thread.id) ? ' active' : '') + '"' + threadData + '>\n' + tmpl + '\n</li>\n';
			}

			$(this.data.element.threadsList).html(html);

			if (this.data.environment.threads.length) {
				$(this.data.element.noThreads).hide();
				$(this.data.element.threadsList).show();
			} else {
				$(this.data.element.threadsList).hide();
				$(this.data.element.noThreads).show();
			}

			$(this.data.element.threadsList).children().on('click', function(event) {
				var thread_id = $(this).data('thread-id');

				if (chat.isThreadActive(thread_id)) {
					// thread already active
					return false;
				}

				// activate thread
				chat.setThreadActive(thread_id);
			});

			// Prevent animation in case the list contains a temporary thread, because
			// the system is going to re-build the list on reply request completion.
			// We observed that rebuilding the list while the sorting animation is
			// occurring could cause duplicate items.
			if (sort && !gotNewThread) {
				// animate sort to display most recent threads on top
				$(this.data.element.threadsList).animatedSort({
					column: 'data-thread-datetime',
					direction: 'desc',
				});

				// sort threads list too in order to commit the real ordering
				this.data.environment.threads.sort(function(a, b) {
					if (a.last_updated < b.last_updated) {
						return 1;
					} 

					if (a.last_updated > b.last_updated) {
						return -1;
					}

					return 0;
				});
			}

			return this;
		}

		/**
		 * Build the chat conversation that belong to the current active thread.
		 * The conversation might not own all the messages.
		 *
		 * @param 	mixed 	start 	The initial offset of the messages to display.
		 * 							If not provided, 0 will be used.
		 * @param 	mixed 	end 	The ending offset of the messages to display.
		 * 							If not provided, all the cached messages will be shown.
		 *
		 * @return 	self
		 */
		buildConversation(start, end) {
			if (this.data.environment.activeThread === undefined) {
				return this;
			}

			var thread = this.getActiveThread();

			if (!thread) {
				return this;
			}

			var html = '';

			if (start === undefined) {
				start = 0;
			}

			if (end === undefined) {
				end = thread.messages.length;
			}

			// define queue for failed messages
			var failedQueue = [];

			for (var i = end - 1; i >= start; i--) {
				var message = thread.messages[i];

				if (message.hasError) {
					// push the message within the failed queue, then go to next item
					failedQueue.push(message.id);

					// unset error to avoid sending it twice
					message.hasError = false;
					continue;
				}

				// get message template (message, false: no animation, true: get buffer)
				html += this.drawMessage(message, false, true);
			}

			$(this.data.element.conversation).prepend(html);

			if (start == 0) {
				// in case the chat was empty, auto-scroll conversation to the last message
				this.scrollToBottom();
			}

			// we need to iterate all the failed messages and retry to send them
			for (var i = 0; i < failedQueue.length; i++) {
				// remove thread message from list
				var mess = this.removeThreadMessage(thread.id, failedQueue[i]);
				
				if (mess) {
					// re-send message content
					this.send(mess.content);
				}
			}

			return this;
		}

		/**
		 * Initializes the conversation for the active thread.
		 *
		 * @return 	self
		 */
		startConversation() {
			var chat = this;

			$(this.data.element.conversation).html('');

			// setup environment vars
			this.data.environment.isLoadingOlderMessages = false;

			var thread = this.getActiveThread();

			// clear scroll event
			$(this.data.element.conversation).off('scroll');

			if (thread && thread.messagesLength < parseInt(thread.tot_messages)) {
				// setup scroll event to load older messages
				$(this.data.element.conversation).on('scroll', function() {
					if (chat.data.environment.isLoadingOlderMessages) {
						// ignore if we are currently loading older messages
						return;
					}

					// get scrollable pixel
					var scrollHeight = this.scrollHeight - $(this).outerHeight();
					// get scroll top
					var scrollTop    = this.scrollTop;

					// start loading older messages only when scrollbar
					// hits the first half of the whole scrollable height
					if (scrollTop / scrollHeight < 0.5) {
						// load older chat messages
						chat.loadPreviousMessages();
					}

				});
			}

			return this;
		}

		/**
		 * Returns the HTML of the requested template.
		 *
		 * @param 	object 	lookup 	The placeholders to inject within the template.
		 *
		 * @return 	string 	The resulting HTML.
		 */
		getTemplate(name, lookup) {
			if (!this.data.template.hasOwnProperty(name)) {
				return '';
			}

			var tmpl = this.data.template[name];

			for (var k in lookup) {
				if (lookup.hasOwnProperty(k)) {
					tmpl = tmpl.replace('{' + k + '}', lookup[k]);
				}
			}

			return tmpl;
		}

		/**
		 * Scrolls the chat conversation to the most recent message.
		 *
		 * @return 	self
		 */
		scrollToBottom() {
			$(this.data.element.conversation).scrollTop($(this.data.element.conversation)[0].scrollHeight + 200);

			return this;
		}

		/**
		 * Checks whether the chat should scroll.
		 * If we are reading older messages, the chat should not scroll.
		 * Contrarily, if we are keeping an eye on the latest messages,
		 * the chat should scroll to the bottom.
		 *
		 * @param 	integer	 threshold 	An optional threshold (30px by default).
		 *
		 * @return 	boolean
		 */
		shouldScroll(threshold) {
			var conversation = $(this.data.element.conversation)[0];

			// total scrollable amount (we need to exclude the chat height from the scroll height)
			var scrollable = conversation.scrollHeight - $(conversation).outerHeight();
			// get difference between current scroll top and total scroll top
			var diff = Math.abs(scrollable - conversation.scrollTop);

			// scroll only in case we are already at the bottom position,
			// with a maximum threshold of 30 pixel
			return diff <= (threshold || 30);
		}

		/**
		 * Returns the reference of the active thread.
		 * Undefined in case the chat doesn't have any threads.
		 *
		 * @return 	mixed 	The active thread, if any. Otherwise undefined.
		 */
		getActiveThread() {
			// get active thread ID
			var activeThread = this.data.environment.activeThread;
			// get active thread object
			return this.getThread(activeThread);
		}

		/**
		 * Checks if the specified thread ID is equals to the ID
		 * of the active thread.
		 *
		 * @return 	boolean
		 */
		isThreadActive(thread_id) {
			// get active thread object
			var activeThread = this.getActiveThread();

			// make sure there is a thread active and check if the IDs are matching
			return activeThread && activeThread.id == thread_id;
		}

		/**
		 * Returns the thread object that matches the specified ID.
		 *
		 * @param 	mixed 	id 	The thread ID.
		 *
		 * @return 	mixed 	The matching object, otherwise null.
		 */
		getThread(id) {
			for (var i = 0; i < this.data.environment.threads.length; i++) {
				var thread = this.data.environment.threads[i];

				if (thread.id == id) {
					return thread;
				}
			}

			return null;
		}

		/**
		 * Returns the index of the message object that matches
		 * the specified ID and that belong to the specified thread.
		 *
		 * @param 	mixed 	 id_thread 	The thread ID.
		 * @param 	mixed 	 id 		The message ID.
		 *
		 * @return 	integer  The index of the matching object, otherwise -1.
		 */
		getThreadMessageIndex(id_thread, id) {
			// get thread
			var thread = this.getThread(id_thread);

			if (thread) {
				// iterate threads
				for (var i = 0; i < thread.messages.length; i++) {
					if (thread.messages[i].id == id) {
						return i;
					}
				}
			}

			return -1;
		}

		/**
		 * Returns the message object that matches the specified ID
		 * and that belong to the specified thread.
		 *
		 * @param 	mixed 	id_thread 	The thread ID.
		 * @param 	mixed 	id 			The message ID.
		 *
		 * @return 	mixed 	The matching object, otherwise null.
		 */
		getThreadMessage(id_thread, id) {
			// get thread
			var thread = this.getThread(id_thread);

			if (thread) {
				// iterate threads
				for (var i = 0; i < thread.messages.length; i++) {
					if (thread.messages[i].id == id) {
						return thread.messages[i];
					}
				}
			}

			return null;
		}

		/**
		 * Removes the message object that matches the specified ID
		 * and that belong to the specified thread.
		 *
		 * @param 	mixed 	 id_thread 	The thread ID.
		 * @param 	mixed 	 id 		The message ID.
		 * @param 	boolean  strict 	True to remove the message from the chat too.
		 *
		 * @return 	mixed 	The removed object on success, otherwise false.
		 */
		removeThreadMessage(id_thread, id, strict) {
			// get thread
			var thread = this.getThread(id_thread);

			if (thread) {
				// iterate threads
				for (var i = 0; i < thread.messages.length; i++) {
					if (thread.messages[i].id == id) {
						// check if the chat message should be removed
						if (strict) {
							if ($('#'+ id).prev().hasClass('is-a-separator')) {
								// remove previous separator too
								$('#' + id).prev().remove();
							}

							// remove chat element
							$('#' + id).remove();
						}

						return thread.messages.splice(i, 1)[0];
					}
				}
			}

			return false;
		}

		/**
		 * Returns the latest message sent/received.
		 * In this case "latest" means "most recent".
		 *
		 * @return 	mixed 	The latest message if any, otherwise null.
		 */
		getLatestMessage() {
			var msg = null;

			for (var i = 0; i < this.data.environment.threads.length; i++) {
				var thread = this.data.environment.threads[i];

				var k = 0;

				// iterate until we find a message with a valid ID
				while (k < thread.messages.length && !isFinite(thread.messages[k].id)) {
					k++;
				}

				// make sure we have a message to evaluate
				if (k < thread.messages.length) {
					// keep message only if not set or the new ID is higher than the kept one
					if (msg === null || parseInt(msg.id) < parseInt(thread.messages[k].id)) {
						msg = thread.messages[k];
					}
				}
			}

			return msg;
		}

		/**
		 * Returns the latest message received that needs to be read.
		 * In this case "latest" means "most recent".
		 *
		 * @param 	mixed 	id_thred  The thread to use.
		 *
		 * @return 	mixed 	The latest unread message if any, otherwise null.
		 */
		getLatestUnreadMessage(id_thread) {
			var thread = null;

			if (typeof id_thread === 'object') {
				// use the passed thread object
				thread = id_thread;
			} else {
				// find thread
				thread = this.getThread(id_thread);
			}

			if (thread) {
				// iterate the messages
				for (var i = 0; i < thread.messages.length; i++) {
					var msg = thread.messages[i];
					// make sure the message is valid, needs to be read and
					// wasn't posted by the sender
					if (isFinite(msg.id) && !msg.read_dt && !this.isSender(msg)) {
						return msg;
					}
				}
			}

			return null;
		}

		/**
		 * Sets the thread that matches the specified ID as active.
		 * After invoking this method, the chat conversation is always
		 * initialized.
		 *
		 * @return 	self
		 */
		setThreadActive(thread_id) {
			// detach active class from other threads
			$(this.data.element.threadsList)
				.find('*.active')
					.removeClass('active');

			// attach active class to rquested thread
			$(this.data.element.threadsList)
				.find('*[data-thread-id="' + thread_id + '"]')
					.addClass('active');

			this.data.environment.activeThread = thread_id;

			// read notifications
			this.readNotifications(thread_id);

			/**
			 * After switching thread we need to fetch the payload of the last
			 * message in order to build the proper input for the response.
			 */
			this.renderInput();

			// clear conversation and re-build it
			this.startConversation().buildConversation();

			return this;
		}

		/**
		 * Returns the next identifier to use for DOM chat messages.
		 *
		 * @string
		 */
		getNextID() {
			return 'msg-' + (++this.data.environment.id);
		}

		/**
		 * Checks if the current client is the sender of the message.
		 *
		 * @return 	boolean
		 */
		isSender(message) {
			/**
			 * Check if the sender type is equals to hotel or 1, because
			 * we may fetch a temporary record that hasn't been yet replaced
			 * with its real value.
			 */
			var is = message.sender_type.toString().match(/^(hotel|host|1)$/i) ? true : false;

			/**
			 * In case we are visiting the page from the front-end,
			 * we need to negate the value as we are the sender
			 * only if the previous regex IS NOT verified.
			 */
			if (this.data.environment.client === 'site') {
				is = !is;
			}
			
			return is;
		}

		/**
		 * Collects the specified message within the internal state and
		 * pushes it within the chat conversation.
		 *
		 * @param 	integer  id 	  The message ID.
		 * @param 	string 	 message  The message content.
		 * @param 	integer  type 	  The message type (1 for sender, 2 for recipient).
		 *
		 * @return 	mixed 	 The collected object on success, otherwise false.
		 */
		collect(id, message, type) {
			// get active thread
			var thread = this.getActiveThread();

			if (!thread) {
				var subject = null;

				/**
				 * If we are here, probably we tried to send a message without
				 * available threads. So, instead aborting the request, we need to 
				 * prompt an input to define the thread's subject. In case the input
				 * is empty, we could use a default text, such as "Thread #1".
				 */
				if (this.data.environment.client == 'admin') {
					subject = prompt(Joomla.JText._('VCM_CHAT_THREAD_TOPIC'), 'Thread #1');
				} else {
					// use always a predefined text for guest client
					subject = Joomla.JText._(this.data.lang.defthread);
				}

				if (subject === null) {
					// the user cancelled the process
					return false;
				}

				/**
				 * Before to proceed with the message collection, we have to create
				 * a dummy thread to be displayed within the list. It is important
				 * to set a temporary ID (e.g. "tmp-thread-1") so that we could
				 * understand whether we are going to reply to an existing thread
				 * or if we are opening a new conversation.
				 */
				thread = {
					id: 	 	   'tmp-thread-' + (this.data.environment.threads.length + 1),
					idorder: 	   this.data.environment.idOrder,
					channel: 	   this.data.environment.channel,
					subject: 	   subject || 'Thread',
					messages: 	   [],
					last_updated:  DateHelper.toStringUTC(new Date()),
				};

				// initialise thread
				this.initThread(thread);

				// collect thread
				this.data.environment.threads.push(thread);

				// make thread active
				this.setThreadActive(thread.id);
			}

			// build dummy object
			var dummy = {
				id: 		 id,
				idthread: 	 thread.id,
				content: 	 message,
				sender_type: type,
				dt: 		 DateHelper.toStringUTC(new Date()),
				// keep a copy of the subject
				subject: 	 thread.subject,
			};

			if (this.data.environment.attachments.length) {
				// push attachments images within data
				dummy.attachments = this.data.environment.attachments.map(function(attach) {
					// take URL only
					return attach.url;
				});
			}

			// draw message within the chat
			this.drawMessage(dummy, true);

			// push dummy data within the thread messages
			thread.messages.unshift(dummy);

			// rebuild threads to update datetime
			this.buildThreads(true);

			return dummy;
		}

		/**
		 * Draws the given message within the chat conversation.
		 * The method doesn't check whether the message thread matches
		 * the current one.
		 *
		 * @param 	object 	 message  The message to draw.
		 * @param 	boolean  animate  True to animate the message entrance.
		 * @param 	boolean  buffer   True to return the message template.
		 *
		 * @return 	mixed 	 In case of buffer, the template string will be returned
		 * 					 instead of being appended within the DOM. Otherwise,
		 * 					 this object will be returned for chaining.
		 */
		drawMessage(message, animate, buffer) {
			// get active thread
			var thread = this.getActiveThread();

			if (!thread) {
				// no thread found, abort
				return false;
			}

			var chat = this;
			var tmpl = '';

			// get index of message
			var index = this.getThreadMessageIndex(message.idthread, message.id);

			if (index != -1) {
				// get next message
				index++;
			} else {
				// use first index available as our message might be not yet in the list
				index = 0;
			}

			// get last message sent/received for this thread
			var prev = thread.messages.length ? thread.messages[index] : null;

			if (!prev || DateHelper.diff(message.dt, prev.dt, 'minutes') > 10) {
				// write date separator because have passed more than 10 minutes since the previous message
				tmpl += this.getDateSeparator(message.dt);
			}

			// use custom element ID
			var elem_id = isFinite(message.id) ? 'delivered-' + message.id : message.id;

			// make content HTML-safe
			var content = message.content;

			if (!isFinite(message.id)) {
				// make content HTML-safe only for new messages
				content = content.htmlentities();
			}

			// fetch message content
			var content = this.renderMessageContent(content);

			// define message lookup for template
			var lookup = {
				class: 	 'message-content ' + (animate ? 'need-animation ' : '') + (this.isSender(message) ? 'sent' : 'received') + (!content.length ? ' message-empty' : ''),
				id: 	 elem_id,
				message: content.replace(/\n/g, '<br />'),
			};

			// get message template
			tmpl += this.getTemplate('message', lookup);

			if (typeof message.attachments === 'string') {
				try {
					// try to decode the JSON attachments
					message.attachments = JSON.parse(message.attachments);
				} catch (err) {
					// malformed string, use empty array
					message.attachments = [];
				}
			}

			var hasAttachments = message.attachments && message.attachments.length;

			// check if the message has some attachments
			if (hasAttachments) {
				// iterate attachments
				for (var i = 0; i < message.attachments.length; i++) {
					var attachment   = message.attachments[i];
					var attachLookup = Object.assign({}, lookup);

					attachLookup.id += '-attachment-' + (i + 1);
					attachLookup.class += ' is-attachment';

					// get proper media element
					attachLookup.message = this.getMedia(attachment, animate);

					// check if we have something to show
					if (attachLookup.message) {
						// append message template to buffer
						tmpl += this.getTemplate('message', attachLookup);
					}
				}
			}

			if (buffer) {
				// return template in case of no animation
				return tmpl;
			}

			// append HTML to conversation box
			$(this.data.element.conversation).append(tmpl);
			
			if (animate) {
				// setup timeout to perform entrance animation
				setTimeout(function() {
					// in case of attachments, we need to find all the messages that
					// starts with the message ID
					var selector = hasAttachments ? '*[id^="' + elem_id + '"]' : '#' + elem_id;
					// ease in message
					$(selector).find('*.need-animation').removeClass('need-animation');

					chat.scrollToBottom();
				}, 32);
			}

			return this;
		}

		/**
		 * Renders the message content in order to replace certain tokens
		 * with a user-friendly representation.
		 * For example, an e-mail address could be wrapped within a link to
		 * open the mail app.
		 *
		 * @param 	string 	content 	The text to fetch.
		 *
		 * @return 	string 	The resulting string.
		 */
		renderMessageContent(content) {
			// get parsers list and sort by priority DESC
			var pool = Object.values(this.contentParsers || {}).sort(function(a, b) {
				if (a.priority < b.priority) {
					return 1;
				}

				if (a.priority > b.priority) {
					return -1;
				}

				return 0;
			});

			for (var i = 0; i < pool.length; i++) {
				// keep a temporary flag
				var tmp = content.toString();

				// run parser callback (use tmp in case the callback forgot to the return the value)
				content = pool[i].callback(tmp) || tmp;
			}

			return content;
		}

		/**
		 * Attaches a callback that will be used to parse the contents.
		 * In case a function with the same ID already exists, that function
		 * will be replaced with this one.
		 *
		 * @param 	string 	  id 	    The parser identifier.
		 * @param 	function  callback  The function to run while parsing the contents.
		 * @param 	integer   priority  The callback priority. The higher the value, the
		 * 								higher the priority.
		 *
		 * @return 	self
		 */
		attachContentParser(id, callback, priority) {
			if (this.contentParsers === undefined) {
				this.contentParsers = {};
			}

			// register callback
			this.contentParsers[id] = {
				callback: callback,
				priority: priority || 10,
			};

			return this;
		}

		/**
		 * Tries to detach the parser that matches the specified id.
		 *
		 * @param 	string 	 id  The parser identifier.
		 *
		 * @return 	boolean  True on success, false otherwise.
		 */
		detachContentParser(id) {
			// make sure the pool contains the id
			if (this.contentParsers !== undefined && this.contentParsers.hasOwnProperty(id)) {
				// detach parser
				delete this.contentParsers[id];

				return true;
			}

			return false;
		}

		/**
		 * Returns the most appropriate DOM element according to the specified URL.
		 * In case of a media file, the URL will be wrapped within a <img> tag.
		 * Otherwise a Font Icon will be used instead.
		 *
		 * @param 	string 	 url 	  The URL to check.
		 *
		 * @return 	string 	 The HTML media string.
		 */
		getMedia(url) {
			// always get a string
			if (!url.toString) {
				return '';
			}

			url = url.toString();

			// extract file name from URL
			var name = url.match(/\/([^\/]+)$/);

			if (!name) {
				// there is something wrong with the attachment, use a broken icon
				return '<i class="fas fa-unlink" title="' + url + '"></i>';
			}

			// use only last capturing group
			name = name.pop();

			var onclick = "window.open('" + url + "', '_blank')";
			var onload  = "VCMChat.getInstance().onMediaLoaded(this)";

			// check for images
			if (url.match(/\.(a?png|bmp|gif|ico|jpe?g|svg)$|(^http.+airbnb\.com\/)/i)) {
				return '<img src="' + url + '" onclick="' + onclick + '" onload="' + onload + '" title="' + name + '" />';
			}

			// check for playable video files
			if (url.match(/\.(mp4|mov|ogm|webm)$/i)) {
				return '<video controls onloadeddata="' + onload + '" title="' + name + '">\n' +
					'<source src="' + url + '" />\n' +
				'</video>';
			}

			// check for non-playable video files
			if (url.match(/\.(3gp|asf|avi|divx|flv|mkv|mp?g|wmv|xvid)$/i)) {
				return '<i class="fas fa-file-video" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for playable audio files
			if (url.match(/\.(aac|m4a|mp3|opus|wave?)$/i)) {
				return '<audio controls onloadeddata="' + onload + '" title="' + name + '">\n' +
					'<source src="' + url + '" />\n' +
				'</audio>';
			}

			// check for non-playable audio files
			if (url.match(/\.(ac3|aiff|flac|midi?|wma)$/i)) {
				return '<i class="fas fa-file-audio" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for archives
			if (url.match(/\.(zip|tar|rar|gz|bzip2)$/i)) {
				return '<i class="fas fa-file-archive" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for PDF
			if (url.match(/\.pdf$/i)) {
				return '<i class="fas fa-file-pdf" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for documents
			if (url.match(/\.(docx?|rtf|odt)$/i)) {
				return '<i class="fas fa-file-word" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for excel-like sheets
			if (url.match(/\.(xlsx?|csv|ods)$/i)) {
				return '<i class="fas fa-file-excel" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for presentations
			if (url.match(/\.(ppsx?|odp)$/i)) {
				return '<i class="fas fa-file-powerpoint" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// check for plain text documents
			if (url.match(/\.(txt|md|markdown)$/i)) {
				return '<i class="fas fa-file-alt" onclick="' + onclick + '" title="' + name + '"></i>';
			}

			// use standard file
			return '<i class="fas fa-file" onclick="' + onclick + '" title="' + name + '"></i>';
		}

		/**
		 * Handler invoked every time a media file has been loaded.
		 *
		 * @param 	mixed 	element  The media element.
		 *
		 * @return 	void
		 */
		onMediaLoaded(element) {
			// check if we should scroll after loading a media file,
			// because if we are loading previous file, we don't
			// need to scroll down
			if (this.shouldScroll(element.offsetHeight + 30)) {
				// scroll down
				this.scrollToBottom();
			}
		}

		/**
		 * Creates a new progress bar.
		 *
		 * @return 	string 	The progress bar ID.
		 */
		createProgressBar() {
			if (this.data.environment.idProgress === undefined) {
				this.data.environment.idProgress = 0;
			}

			// increment ID
			var id = 'progress-bar-' + (++this.data.environment.idProgress);

			// create progress bar
			$(this.data.element.progressBar)
				.append('<div class="chat-progress-bar" id="' + id + '"><div>&nbsp;</div></div>')
				.parent()
					.show();

			return id;
		}

		/**
		 * Removes the specified progress bar.
		 *
		 * @param 	string 	id 	The progress bar ID.
		 *
		 * @return 	self
		 */
		removeProgressBar(id) {
			$(this.data.element.progressBar).find('#' + id).remove();

			return this;
		}

		/**
		 * Updates the progress value of the specified bar.
		 *
		 * @param 	string 	 id 	   The progress bar ID.
		 * @param 	integer  progress  The progress amount.
		 *
		 * @return 	self
		 */
		updateProgressBar(id, progress) {
			progress = Math.max(0, progress);
			progress = Math.min(100, progress);

			$(this.data.element.progressBar).find('#' + id + ' > div').width(progress + '%').html(progress + '%');

			return this;
		}

		/**
		 * Reads the pending notifications of the specified thread.
		 *
		 * @param 	mixed 	id 	The thread identifier.
		 * 						If not specified, the active one will be used.
		 *
		 * @return 	self
		 */
		readNotifications(id) {
			var chat   = this;
			var thread = null;

			if (!id) {
				// get active thread if no ID provided
				thread = this.getActiveThread();
			} else {
				// get specified thread
				thread = this.getThread(id);
			}

			if (!thread) {
				// no thread found, abort
				return this;
			}

			// make sure we have something to read
			if (thread.notifications > 0) {
				// read all thread notifications
				thread.notifications = 0;

				// re-build threads
				this.buildThreads();
			}

			// use AJAX to read all the messages that belong to the requested thread
			var unread = this.getLatestUnreadMessage(thread.id);

			if (unread) {
				// perform AJAX request to read messages
				UIAjax.do(
					// end-point URL
					this.data.environment.url,
					// POST data
					{
						task: 		'chat.read_messages',
						channel: 	thread.channel || this.data.environment.channel,
						secret: 	this.data.environment.secret,
						id_order: 	thread.idorder,
						id_message: unread.id,
						datetime:   DateHelper.toStringUTC(new Date()),
					},
					function(resp) {
						try {
							// try parsing JSON string
							resp = JSON.parse(resp);
						} catch (err) {
							// something went wrong with JSON decoding, log and abort callback
							console.error(err, resp);
							return;
						}

						if (!resp.count) {
							// no read message, do nothing
							return;
						}

						// mark all read messages
						for (var i = 0; i < thread.messages.length; i++) {
							var msg = thread.messages[i];
							
							// check if the message should be read
							if (msg.id <= unread.id && msg.read_dt === null && !chat.isSender(msg)) {
								// read message with current time
								msg.read_dt = resp.datetime;
							}
						}
					}
				);
			}

			return this;
		}

		/**
		 * Returns the HTML to use for a date separator.
		 *
		 * @param 	string|object 	datetime 	The datetime to use.
		 *
		 * @return 	string 	The HTML separator.
		 */
		getDateSeparator(datetime) {
			var dt_str = '';

			if (DateHelper.isToday(datetime)) {
				// current day: get formatted time
				dt_str = Joomla.JText._(this.data.lang.today);
			} else if (DateHelper.isYesterday(datetime)) {
				// previous day: use "yesterday"
				dt_str = Joomla.JText._(this.data.lang.yesterday);
			} else if ((dt_str = DateHelper.diff(datetime, new Date(), 'days')) < 7) {
				var tmp = new Date();
				tmp.setDate(tmp.getDate() - dt_str);
				dt_str = tmp.toLocaleDateString([], {weekday: 'long'});
			} else {
				// use formatted date
				dt_str = DateHelper.getFormattedDate(datetime);
			}

			var lookup = {
				utc: 		DateHelper.toStringUTC(datetime),
				datetime: 	dt_str + ', ' + DateHelper.getFormattedTime(datetime),
				class: 		'is-a-separator',
			};

			return this.getTemplate('datetime', lookup);
		}

		/**
		 * Merges the threads and the related messages with the ones
		 * stored within the internal state.
		 *
		 * @param 	array 	resp 	The threads list.
		 *
		 * @return 	array 	Returns a list containing all the new messages.
		 */
		mergeThreads(resp) {
			var newMessages = [];

			// update threads messages list
			for (var i = 0; i < resp.length; i++) {
				// get thread that matches the current ID
				var thread = this.getThread(resp[i].id);

				if (!thread) {
					// initialise thread
					this.initThread(resp[i]);
					resp[i].isNew = true;

					// push new thread with all messages
					this.data.environment.threads.push(resp[i]);

					// concat all thread messages to list
					newMessages = newMessages.concat(resp[i].messages);
				} else {
					// always refresh thread topic after receiving new messages
					thread.subject = resp[i].subject;
					
					// iterate all messages
					for (var k = resp[i].messages.length - 1; k >= 0; k--) {
						// get thread message that matches the current ID
						var message = this.getThreadMessage(thread.id, resp[i].messages[k].id);

						if (!message) {
							// prepend message within the list
							thread.messages.unshift(resp[i].messages[k]);

							// register message only if new
							newMessages.push(resp[i].messages[k]);
						}
					}
				}
			}

			return newMessages;
		}

		/**
		 * Uploads the given files.
		 *
		 * @param 	mixed 	files 	The files list.
		 *
		 * @return 	self
		 */
		uploadAttachments(files) {
			var chat = this;

			var thread = this.getActiveThread();

			// create form data for upload
			var formData = new FormData();

			// inject order data
			formData.append('id_order', chat.data.environment.idOrder);
			formData.append('channel', thread && thread.channel ? thread.channel : chat.data.environment.channel);
			formData.append('secret', chat.data.environment.secret);
			formData.append('task', 'chat.upload_attachments');

			// iterate files and append to form data
			for (var i = 0; i < files.length; i++) {
				formData.append('attachments[]', files[i]);
			}

			// create progress bar
			var id_progress = chat.createProgressBar();

			UIAjax.upload(
				// end-point URL
				this.data.environment.url,
				// file post data
				formData,
				// success callback
				function(resp) {
					// remove progress bar
					chat.removeProgressBar(id_progress);

					try {
						// try parsing JSON string
						resp = JSON.parse(resp);
					} catch (err) {
						// something went wrong with JSON decoding, log and abort callback
						console.error(err, resp);
						return;
					}

					for (var i = 0; i < resp.length; i++) {
						// register uploaded attachment
						chat.registerAttachment(resp[i]);
					}
				},
				// failure callback
				function(error) {
					// remove progress bar
					chat.removeProgressBar(id_progress);

					// raise alert
					alert(error.responseText);
				},
				// progress callback
				function(progress) {
					// update progress bar
					chat.updateProgressBar(id_progress, progress);
				},
			).critical();

			return this;
		}

		/**
		 * Registers the file within the attachments bar.
		 *
		 * @param 	object 	file 	The file to attach.
		 *
		 * @return 	self
		 */
		registerAttachment(file) {
			var chat = this;

			// push file within the list
			this.data.environment.attachments.push(file);

			// fetch file name and ID
			file.fullName   = file.name + (file.extension ? '.' + file.extension : '');
			file.id 		= file.filename.replace(/\.[^.]*$/, '');

			$(this.data.element.uploadsBar)
				.append('<span class="chat-attachment" id="' + file.id + '">' + file.fullName + '<i class="fas fa-times"></i></span>')
				.parent()
					.show();

			// register event to remove attachment after clicking the TIMES icon
			$('#' + file.id).find('i.fa-times').on('click', function(event) {
				// remove attachment
				chat.removeAttachment(file);
			});

			return this;
		}

		/**
		 * Returns the index of the specified attachment.
		 *
		 * @param 	mixed 	 file 	The file object or its ID.
		 *
		 * @return 	integer  The file index on success, otherwise -1.
		 */
		getAttachmentIndex(file) {
			var id = null

			if (typeof file === 'object') {
				id = file.id;
			} else {
				id = file;
			}

			for (var i = 0; i < this.data.environment.attachments.length; i++) {
				if (this.data.environment.attachments[i].id === id) {
					return i;
				}
			}

			return -1;
		}

		/**
		 * Removes the specified attachment by unlinking it too.
		 *
		 * @param 	object 	 file 	The file object to remove.
		 *
		 * @return 	self
		 */
		removeAttachment(file) {
			// get attachment index
			var index = this.getAttachmentIndex(file);

			if (index != -1) {
				// remove attachment box
				$('#' + file.id).remove();

				if (this.data.environment.attachments.length > 1) {
					// splice attachments array
					this.data.environment.attachments.splice(index, 1);
				} else {
					// clear all as we are going to have an empty list
					this.clearAttachments();
				}

				// make AJAX request to unlink the specified attachment
				UIAjax.do(
					// end-point URL
					this.data.environment.url,
					// POST data
					{
						task: 	  'chat.remove_attachment',
						filename: file.filename,
					}
				);
			}

			return this;
		}

		/**
		 * Clears the attachments list.
		 *
		 * @return 	self
		 */
		clearAttachments() {
			// clear attachments
			this.data.environment.attachments = [];

			// hide uploads bar
			$(this.data.element.uploadsBar)
				.html('')
				.parent()
					.hide();

			return this;
		}

		/**
		 * Triggers the specified event.
		 *
		 * @param 	string 	name 	The event name.
		 * @param 	mixed 	data 	The data to inject within event.detail property.
		 *
		 * @return 	self
		 */
		triggerEvent(name, data) {
			// create CustomEvent by injecting our own data
			var event = new CustomEvent(name, {detail: data});

			// dispatch event from window
			window.dispatchEvent(event);
			
			return this;
		}

		/**
		 * AJAX call used to load older messages of the active thread.
		 * This function is usually invoked when the scroll hits the first half
		 * of the conversation.
		 *
		 * @return 	self
		 */
		loadPreviousMessages() {
			if (this.data.environment.isLoadingOlderMessages) {
				// do not proceed in case we are already loading something
				return this;
			}

			// get active thread
			var thread = this.getActiveThread();

			if (!thread) {
				// do thread active, do nothing
				return this;
			}

			// mark loading flag
			this.data.environment.isLoadingOlderMessages = true;

			var chat  = this;
			var limit = 20;

			// make AJAX request to load older messages
			UIAjax.do(
				// end-point URL
				this.data.environment.url,
				// POST data
				{
					task: 		'chat.load_older_messages',
					channel: 	thread.channel || this.data.environment.channel,
					secret: 	this.data.environment.secret,
					id_thread:	thread.id,
					id_order: 	thread.idorder,
					start: 		thread.messagesLength,
					limit: 		limit,
					/**
					 * We need to pass the initial date time in order to exclude 
					 * all the messages that are newer than the latest message we got 
					 * when the page was loaded.
					 *
					 * This will avoid errors while retriving older messages
					 * as new records would shift the current limits.
					 */
					datetime:   thread.initialDatetime,
				},
				// success callback
				function(resp) {
					// make loading available again
					chat.data.environment.isLoadingOlderMessages = false;

					try {
						// try parsing JSON string
						resp = JSON.parse(resp);
					} catch (err) {
						// something went wrong with JSON decoding, log and abort callback
						console.error(err, resp);
						return;
					}

					var conversation = $(chat.data.element.conversation)[0];

					// keep current scroll
					var currentScrollTop    = conversation.scrollTop;
					var currentScrollHeight = conversation.scrollHeight;

					// update count of loaded thread messages
					thread.messagesLength += resp.length;

					// get current index
					var start = thread.messages.length;
					var end   = start + resp.length;

					// push messages within the list
					for (var i = 0; i < resp.length; i++) {
						thread.messages.push(resp[i]);
					}

					// check if the thread is still active because the user may have opened
					// a different thread while the request was loading
					if (chat.isThreadActive(thread.id)) {

						// check if there are no more messages to load
						if (thread.messagesLength >= thread.tot_messages) {
							// turn off scroll handler
							$(chat.data.element.conversation).off('scroll');
						}

						// build conversation messages
						chat.buildConversation(start, end);

						// Recalculate scroll position.
						// The new scroll top position will be increased by the difference between
						// the old scroll height and the new one.
						var newScrollTop = currentScrollTop + (conversation.scrollHeight - currentScrollHeight);
						$(conversation).scrollTop(newScrollTop);
					}
				},
				// failure callback
				function(error) {
					// make loading available again
					chat.data.environment.isLoadingOlderMessages = false;
				}
			);
		}

		/**
		 * AJAX call used to synchronize the threads and the messages.
		 * This should be used to load the messages that haven't been
		 * downloaded by the system.
		 *
		 * @return self
		 */
		synchronizeThreads() {
			var chat = this;

			// get latest message to evaluate a threshold
			var latest = this.getLatestMessage();

			// make request to synchronize threads
			var xhr = UIAjax.do(
				// end-point URL
				this.data.environment.url,
				// POST data
				{
					task: 	   'chat.sync_threads',
					// do not use the thread channel, but rather the booking channel
					channel:   this.data.environment.channel,
					id_order:  this.data.environment.idOrder,
					secret:    this.data.environment.secret,
					threshold: latest ? latest.id : 0,
				},
				// success callback
				function(resp) {
					try {
						// try parsing JSON string
						resp = JSON.parse(resp);
					} catch (err) {
						// something went wrong with JSON decoding, log and abort callback
						console.error(err, resp);
						return;
					}

					if (!resp.length) {
						// do nothing in case the response is empty
						return;
					}

					// get active thread
					var thread = chat.getActiveThread();

					// use ID only
					var thread_id = thread ? thread.id : 0;

					// check if the chat should scroll after collecting new messages
					var should_scroll = chat.shouldScroll();

					// update threads and messages
					var newMessages = chat.mergeThreads(resp);

					if (!newMessages.length) {
						// stop process in case nothing has changed
						return;
					}

					// init object to track whether unactive threads as new notifications
					var threadsNotif = {};

					// collect new messages
					for (var i = 0; i < newMessages.length; i++) {
						// check if thread is the same
						if (newMessages[i].idthread === thread_id) {
							// draw message (animation needed)
							chat.drawMessage(newMessages[i], true);
						}

						// get thread message
						var tmp = chat.getThread(newMessages[i].idthread);

						if (tmp) {
							if (tmp.isNew) {
								// reset notifications for new threads,
								// otherwise we would have a badge with a
								// doubled amount
								delete tmp.isNew;
								tmp.notifications = 0;
							}

							// increase notifications counter
							tmp.notifications++;
						}
					}

					// trigger event
					chat.triggerEvent('chatsync', {
						notifications: newMessages.length,
					});

					// flush notifications for active chat
					chat.readNotifications();

					/**
					 * Use bottom scroll only in case the message is visible
					 * within the scroll. In this way, if we are reading older messages
					 * we won't pushed back at the page bottom. Contrarily, in case we
					 * are keeping an eye on the latest messages, the chat will be scrolled
					 * automatically.
					 */
					if (should_scroll) {
						// scroll conversation to bottom
						chat.scrollToBottom();
					}

					// rebuild threads
					chat.buildThreads(true);

					/**
					 * After registering the thread messages we
					 * need to fetch the payload in order to build the
					 * proper input for the response.
					 */
					chat.renderInput();
				}
			);
		
			// Callback must be invoked after chat.send request.
			// In case this process ends while send() request is still
			// running, the callback will be pushed within a queue of promises.
			// Promises are automatically flushed after the completion of the 
			// last running send() process.
			xhr.after('chat.send');
		}

		/**
		 * AJAX call used to send the message to the recipient of the active thread.
		 * The message is always pushed within the chat even if the connection fails.
		 * In that case, the message will report a button that could be used to re-send
		 * the message.
		 *
		 * @param 	mixed 	message  The message to send.
		 *
		 * @return 	self
		 */
		send(message) {
			var chat = this;
			var id   = null;
			var data = null;

			if (typeof message === 'object') {
				// use passed data
				data = message;
				id   = message.id;
			} else {
				// validate message as string
				if (!message.length) {
					return this;
				}

				// trim message
				message = message.trim();

				// generate temporary ID
				id = this.getNextID();

				// in case of client, place the bubble on the left-side (0)
				var type = this.data.environment.client == 'admin' ? 1 : 0;
				// build chat bubble
				data = this.collect(id, message, type);

				if (data) {
					// overwrite attachments list with filenames
					data.attachments = this.data.environment.attachments.map(function(file) {
						return file.filename;
					});

					// clear attachments bar
					this.clearAttachments();
				}
			}

			if (!data) {
				// something went wrong while collecting the message, abort
				return this;
			}

			/**
			 * Since this method can be used to open a new thread or to
			 * reply to an existing message, we should check what we are 
			 * going to perform.
			 *
			 * In case the thread ID doesn't have only numbers, we are 
			 * opening a new thread.
			 */
			var isNewThread = data.idthread.toString().match(/^[\d]+$/) ? false : true;

			/**
			 * Before all, in case of thread opening, we should disable the textarea
			 * temporarily until the thread has been validated and replaced with its
			 * real ID.
			 */
			if (isNewThread) {
				$(this.data.element.textarea).prop('disabled', true);
			}

			// Always re-render input after sending a message.
			// At this point, a textarea should be always used.
			this.renderInput();

			// get thread object
			var thread = chat.getThread(data.idthread);

			// make request to reply to an existing message (CRITICAL)
			var xhr = UIAjax.do(
				// end-point URL
				this.data.environment.url,
				// POST data
				{
					task: 		 'chat.thread_message_reply',
					// unset ID in case of thread opening
					id_thread:   isNewThread ? 0 : data.idthread,
					channel:     thread && thread.channel ? thread.channel : this.data.environment.channel,
					id_order:    this.data.environment.idOrder,
					secret:      this.data.environment.secret,
					content:     data.content,
					datetime:    data.dt,
					attachments: data.attachments,
					subject: 	 data.subject,
				},
				// success callback
				function(resp) {
					try {
						// try parsing JSON string
						resp = JSON.parse(resp);
					} catch (err) {
						// something went wrong with JSON decoding, log and abort callback
						console.error(err, resp);
						return;
					}

					// get index of dummy message
					var dummyIndex = chat.getThreadMessageIndex(data.idthread, data.id);

					if (dummyIndex != -1) {
						// update message with received response
						thread.messages[dummyIndex] = resp.message;
					}

					// update thread with received details
					Object.assign(thread, resp.thread);

					if (isNewThread) {
						/**
						 * In case of new thread, we might have to reset the active
						 * thread as the ID was changed. VCMChat.setThreadActive()
						 * re-builds automatically the conversation.
						 */
						chat.setThreadActive(thread.id);
						chat.buildThreads();
					}

					// always re-enable the textarea in case of success
					$(chat.data.element.textarea).prop('disabled', false);
				},
				// failure callback
				function(error) {
					/**
					 * Something went wrong while trying to send the message.
					 * Place "re-try" button within the message box so that the user
					 * will be able to resend the message by clicking it.
					 */
					$('#' + id).find('.message-content').append('<i class="fas fa-exclamation-circle"></i>')
						.append($('<div class="message-error-result"></div>').text(error.responseText));

					// register event to re-send the message after clicking the exclamation triangle
					$('#' + id).find('.message-content i.fa-exclamation-circle').on('click', function(event) {
						// remove any possible explanation of the error
						$(this).next('.message-error-result').remove();

						// remove icon from message
						$(this).off('click').remove();

						// re-send the message
						chat.send(data);
					});

					// obtain thread message
					var tmp = chat.getThreadMessage(data.idthread, data.id);

					if (tmp) {
						// mark error
						tmp.hasError = true;
					}

					// always re-enable the textarea in case of success
					$(chat.data.element.textarea).prop('disabled', false);

					/**
					 * In case the thread is no more active, we need to
					 * alert an error message so that the user could understand 
					 * that something went wrong while delivering the message.
					 */
					if (!chat.isThreadActive(data.idthread)) {
						alert(Joomla.JText._(chat.data.lang.senderr));
					}
				}
			);
		
			// mark request as critical
			xhr.critical();
			// set identifier to request
			xhr.identify('chat.send');

			return this;
		}

		/**
		 * Returns the payload of the latest message (sent or received).
		 *
		 * @return 	mixed 	The payload object on success, otherwise false.
		 */
		getInputPayload() {
			// get current thread
			var thread = this.getActiveThread();

			// get latest message that belong to this thread, if any
			var msg = thread && thread.messages.length ? thread.messages[0] : false;

			// check if the message owns a valid payload
			if (!msg || typeof(msg.payload) !== 'object') {
				return false;
			}

			// return message payload
			return Object.assign({}, msg.payload);
		}

		/**
		 * Renders the input according to the specified payload
		 * of the latest received message.
		 *
		 * @param 	mixed 	data 	If specified, these data will be
		 * 							used for rendering.
		 *
		 * @return 	self
		 */
		renderInput(data) {
			if (!data) {
				// get message payload
				data = this.getInputPayload();
			}

			// create default form field data
			var def  = {
				type: 'text',
				hint: Joomla.JText._(this.data.lang.texthint),
			};

			if (!data) {
				// use default form data
				data = def;
			}

			// make sure the input is different than the current one
			if (this.input && Object.equals(this.input.payload, data)) {
				return this;
			}

			var input = null;

			try {
				// get input class
				input = VCMChatField.getInstance(data);
			} catch (err) {
				// the given type seems to be not supported, try to use the default one
				Object.assign(data, def);
				input = VCMChatField.getInstance(data);
			}

			if (this.input) {
				// destroy input set previously
				this.input.onDestroy(this);
			}

			// keep reference to new input
			this.input = input;

			// render input HTML
			var html = this.input.render();

			// set rendered HTML into input box
			$(this.data.element.inputBox).html(html);

			// init new input
			this.input.onInit(this);

			return this;
		}

		/**
		 * Clears all the intervals previously registered.
		 * 
		 * @return 	self
		 */
		destroy() {
			this.timers.forEach((interval_id, index) => {
				clearInterval(interval_id);
			});

			this.timers = [];

			return this;
		}

	}

	/**
	 * VCMChatField class.
	 * Abstract representation of a form field.
	 * This class acts also as a field factory, as the fields
	 * should be instantiated by using the getInstance() static method:
	 * var field = VCMChatField.getInstance({type: 'text'});
	 */
	w['VCMChatField'] = class VCMChatField {

		/**
		 * Returns an instance of the requested field.
		 * The field will be recognized by checking the
		 * type property contained within data argument.
		 *
		 * @param 	object 	data  The field attributes.
		 *
		 * @return 	mixed 	The new field.
		 */
		static getInstance(data) {
			// make sure the type exists
			if (!data.hasOwnProperty('type') || !data.type) {
				throw 'Missing type property';
			}

			// fetch field class name
			var className = 'VCMChatField' + data.type.charAt(0).toUpperCase() + data.type.substr(1);

			// make sure the class exists
			if (!VCMChatField.classMap.hasOwnProperty(data.type)) {
				throw 'Form field [' + className + '] not found';
			}

			// find class
			var _class = VCMChatField.classMap[data.type];

			// instantiate field
			return new _class(data);
		}

		/**
		 * Class constructor.
		 *
		 * @param 	object 	data  The field attributes.
		 */
		constructor(data) {
			this.data = data;
			// keep a copy of the payload which shouldn't be altered
			this.payload = Object.assign({}, data);

			if (!this.data.id) {
				// generate an incremental ID
				if (!VCMChatField.incrementalId) {
					VCMChatField.incrementalId = 0;
				}

				this.data.id = 'chat-answer-field-' + (++VCMChatField.incrementalId);
			}
		}

		/**
		 * Binds the given data.
		 *
		 * @param 	string 	k 	The attribute name.
		 * @param 	mixed 	v 	The attribute value.
		 *
		 * @return 	self
		 */
		bind(k, v) {
			this.data[k] = v;
			
			return this;
		}

		/**
		 * Method used to return the field value.
		 *
		 * @return 	mixed 	The value.
		 */
		getValue() {
			return $('#' + this.data.id).val();
		}

		/**
		 * Method used to set the field value.
		 *
		 * @param 	mixed  val 	The value to set.
		 *
		 * @return 	mixed  The source element.
		 */
		setValue(val) {
			return $('#' + this.data.id).val(val);	
		}

		/**
		 * Enables the input.
		 *
		 * @return 	self
		 */
		enable() {
			$('#' + this.data.id).prop('disabled', false);

			return this;
		}

		/**
		 * Disables the input.
		 *
		 * @return 	self
		 */
		disable() {
			$('#' + this.data.id).prop('disabled', true);

			return this;
		}

		/**
		 * Method used to return the field selector.
		 *
		 * @return 	mixed 	The field selector.
		 */
		getSelector() {
			return '#' + this.data.id;
		}

		/**
		 * Abstract method used to obtain the input HTML.
		 *
		 * @return 	string 	The input html.
		 */
		render() {
			// inherit in children classes
		}

		/**
		 * Abstract method used to initialise the field.
		 * This method is called once the field has been
		 * added within the document.
		 *
		 * @param 	VCMChat  chat 	The chat instance.
		 *
		 * @return 	void
		 */
		onInit(chat) {
			// inherit in children classes

			if (this.data.onInit) {
				// invoke also custom initialize
				this.data.onInit(chat);
			}
		}

		/**
		 * Abstract method used to destroy the field.
		 * This method is called before removing the field
		 * from the document.
		 *
		 * @param 	VCMChat  chat 	The chat instance.
		 *
		 * @return 	void
		 */
		onDestroy(chat) {
			// inherit in children classes

			if (this.data.onDestroy) {
				// invoke also custom destroy
				this.data.onDestroy(chat);
			}
		}

	}

	/**
	 * Form fields classes lookup.
	 */
	VCMChatField.classMap = {};

	/**
	 * VCMChatFieldText class.
	 * This field is used to display a HTML input textarea.
	 */
	w['VCMChatFieldText'] = class VCMChatFieldText extends VCMChatField {

		/**
		 * @override
		 * Method used to obtain the input HTML.
		 *
		 * @return 	string 	The input html.
		 */
		render() {
			// fetch attributes
			var attrs = '';

			if (this.data.name) {
				attrs += 'name="' + this.data.name.escape() + '" ';
			}

			if (this.data.id) {
				attrs += 'id="' + this.data.id.escape() + '" ';
			}

			if (this.data.class) {
				attrs += 'class="' + this.data.class.escape() + '" ';
			}

			if (this.data.hint) {
				attrs += 'placeholder="' + this.data.hint.escape() + '" ';
			}

			if (this.data.value === undefined) {
				this.data.value = this.data.default !== undefined ? this.data.default : '';
			}

			// define default ID for attachment input
			this.data.idAttachment = this.data.id + '-attachment-input';

			// return input
			return '<textarea rows="1" ' + attrs.trim() + '>' + this.data.value + '</textarea>\n' +
				// the initial empty space will align the label properly, as Safari seems to have
				// problems with the calculation of the bounds
				' <label for="' + this.data.idAttachment + '" class="attachment-label"><i class="fas fa-paperclip"></i></label>\n'+
				'<input type="file" id="' + this.data.idAttachment + '" multiple="multiple" style="display:none;" />\n';
		}

		/**
		 * Method used to initialise the field.
		 * This method is called once the field has been
		 * added within the document.
		 *
		 * @param 	VCMChat  chat 	The chat instance.
		 *
		 * @return 	void
		 */
		onInit(chat) {
			// invoke parent first
			super.onInit(chat);

			var padding = 0;
			padding += parseFloat($('#' + this.data.id).css('padding-top').replace(/[^0-9.]/g, ''));
			padding += parseFloat($('#' + this.data.id).css('padding-bottom').replace(/[^0-9.]/g, ''));
			
			// init textarea events
			$('#' + this.data.id).on('input', function () {
				this.style.height = 'auto';
				this.style.height = (this.scrollHeight - padding) + 'px';
			}).on('keydown', function(event) {
				// check if ENTER was pressed without any other modifiers
				if (event.keyCode == 13 && !event.altKey && !event.ctrlKey && !event.shiftKey) {
					chat.send(this.value);
					this.value = '';
					this.style.height = 'auto';

					return false;
				}
			});

			// init attachments upload
			$('#' + this.data.idAttachment).on('change', function(event) {
				// get selected files
				var files = $(this)[0].files;

				// upload attachments
				chat.uploadAttachments(files);

				// unset input file value
				$(this).val(null);
			});
		}

		/**
		 * Method used to destroy the field.
		 * This method is called before removing the field
		 * from the document.
		 *
		 * @param 	VCMChat  chat 	The chat instance.
		 *
		 * @return 	void
		 */
		onDestroy(chat) {
			// invoke parent first
			super.onInit(chat);

			// turn off textarea events before destroying it
			$('#' + this.data.id).off('input').off('keydown');
			// turn off attachments events
			$('#' + this.data.idAttachment).off('change');
		}
		
	}

	// Register class within the lookup
	VCMChatField.classMap.text = VCMChatFieldText;

	/**
	 * VCMChatFieldList class.
	 * This field is used to display a HTML input select.
	 */
	w['VCMChatFieldList'] = class VCMChatFieldList extends VCMChatField {

		/**
		 * Method used to set the field value.
		 *
		 * @param 	mixed  val 	The value to set.
		 *
		 * @return 	mixed  The source element.
		 */
		setValue(val) {
			return super.setValue(val).trigger('chosen:updated').trigger('liszt:updated');
		}

		/**
		 * @override
		 * Method used to obtain the input HTML.
		 *
		 * @return 	string 	The input html.
		 */
		render() {
			// fetch attributes
			var attrs = '';

			if (this.data.name) {
				attrs += 'name="' + this.data.name.escape() + '" ';
			}

			if (this.data.id) {
				attrs += 'id="' + this.data.id.escape() + '" ';
			}

			if (this.data.class) {
				attrs += 'class="' + this.data.class.escape() + '" ';
			}

			if (this.data.multiple) {
				attrs += 'multiple="multiple" ';
			}

			this.data.options = this.data.options || [];

			if (this.data.value === undefined) {
				var def = this.data.multiple ? [] : '';

				this.data.value = this.data.default !== undefined ? this.data.default : def;
			}

			var options = '';

			if (this.data.hint && !this.data.multiple) {
				options += '<option value="">' + this.data.hint + '</option>';
			}

			for (var k in this.data.options) {
				if (!this.data.options.hasOwnProperty(k)) {
					continue;
				}

				var selected = '';

				if ((this.data.multiple && this.data.value.indexOf(k) !== -1) || k == this.data.value) {
					selected = ' selected="selected"';
				}

				options += '<option value="' + k.escape() + '">' + this.data.options[k] + '</option>';
			}

			/**
			 * @todo should we use the attachments here?
			 */

			// define default ID for send button
			this.data.idButton = this.data.id + '-send-button';

			// return input
			return '<select ' + attrs.trim() + '>' + options + '</select>\n' +
				'<button type="button" class="btn" id="' + this.data.idButton + '">Send</button>';
		}

		/**
		 * Method used to initialise the field.
		 * This method is called once the field has been
		 * added within the document.
		 *
		 * @param 	VCMChat  chat 	The chat instance.
		 *
		 * @return 	void
		 */
		onInit(chat) {
			// invoke parent first
			super.onInit(chat);

			// render select using chosen, if supported
			if ($.fn.chosen) {
				$('#' + this.data.id).chosen()
					.next('.chzn-container').width('auto');
			}

			var input = this;

			// register send event
			$('#' + this.data.idButton).on('click', function(event) {
				// get selected value
				var value = input.getValue();

				if (!Array.isArray(value)) {
					value = [value];
				}

				// fetch response message
				var message = value.map(function(k) {
					// recover text message from value
					return input.data.options[k] || k;
				}).filter(function(v) {
					// exclude empty messages
					return v.toString().length ? true : false;
				}).join(', ');

				if (message.length) {
					// send message
					chat.send(message);
				}
			});
		}

		/**
		 * Method used to destroy the field.
		 * This method is called before removing the field
		 * from the document.
		 *
		 * @param 	VCMChat  chat 	The chat instance.
		 *
		 * @return 	void
		 */
		onDestroy(chat) {
			// invoke parent first
			super.onInit(chat);

			// destroy rendered chosen select, if supported
			if ($.fn.chosen) {
				$('#' + this.data.id).chosen('destroy');
			}

			// destroy button event
			$('#' + this.data.idButton).off('click');
		}
		
	}

	// Register class within the lookup
	VCMChatField.classMap.list = VCMChatFieldList;

	/**
	 * DateHelper class.
	 * Helper class used to handle date objects.
	 */
	w['DateHelper'] = class DateHelper {

		/**
		 * Checks if the specified date matches the current day.
		 *
		 * @param 	string|Date  dt  The date to check.
		 *
		 * @return 	boolean 	 True if today, otherwise false.
		 */
		static isToday(dt) {
			// compare specified date with current day
			return DateHelper.isSameDay(dt, new Date());
		}

		/**
		 * Checks if the specified date matches the previous day.
		 *
		 * @param 	string|Date  dt  The date to check.
		 *
		 * @return 	boolean 	 True if yesterday, otherwise false.
		 */
		static isYesterday(dt) {
			// get yesterday date object
			var yesterday = new Date();
			yesterday.setDate(yesterday.getDate() - 1);

			// compare specified date with previous day
			return DateHelper.isSameDay(dt, yesterday);
		}

		/**
		 * Checks if the specified dates are equals without 
		 * considering the related times.
		 *
		 * @param 	string|Date  a  The first date to check.
		 * @param 	string|Date  b  The second date to check.
		 *
		 * @return 	boolean 	 True if equals, otherwise false.
		 */
		static isSameDay(a, b) {
			// convert string to date
			if (typeof a === 'string') {
				a = DateHelper.stringToDate(a);
			}

			// convert string to date
			if (typeof b === 'string') {
				b = DateHelper.stringToDate(b);
			}

			// check if the specified days are matching (exclude time)
			return (a.getDate() == b.getDate() && a.getMonth() == b.getMonth() && a.getFullYear() == b.getFullYear());
		}

		/**
		 * Calculate the difference between the specified dates.
		 * The difference is always an absolute value.
		 *
		 * @param 	string|Date  a  	The first date to check.
		 * @param 	string|Date  b  	The second date to check.
		 * @param 	string 		 unit 	The difference unit [seconds, minutes, hours, days].
		 *
		 * @return 	integer 	 The related difference according to the specified unit.
		 */
		static diff(a, b, unit) {
			// convert string to date
			if (typeof a === 'string') {
				a = DateHelper.stringToDate(a);
			} else {
				// create new instance in order to avoid manipulating the given object
				a = new Date(a);
			}

			// convert string to date
			if (typeof b === 'string') {
				b = DateHelper.stringToDate(b);
			} else {
				// create new instance in order to avoid manipulating the given object
				b = new Date(b);
			}

			// use default unit if not specified
			if (typeof unit === 'undefined') {
				unit = 'seconds';
			}

			// always divide by 1000 to convert milliseconds in seconds
			var div = 1000;

			if (unit.match(/days?/)) {
				// in case of "days" or "day", extract days from seconds
				div = div * 60 * 60 * 24;

				// unset hours, minutes and seconds in order to
				// get the exact difference in days
				a.setHours(0);
				a.setMinutes(0);
				a.setSeconds(0);

				b.setHours(0);
				b.setMinutes(0);
				b.setSeconds(0);
			} else if (unit.match(/hours?/)) {
				// in case of "hours" or "hour", extract hours from seconds
				div = div * 60 * 60;
			} else if (unit.match(/min|minutes?/)) {
				// in case of "min" or "minute" or "minutes", extract minutes from seconds
				div = div * 60;
			}

			// get dates timestamp
			a = a.getTime();
			b = b.getTime();

			// get milliseconds difference between 2 dates
			var diff = Math.abs(b - a);

			// divide difference by the calculated threshold
			return Math.floor(diff / div);
		}

		/**
		 * Formats the specified date according to the browser locale.
		 *
		 * @param 	string|Date  dt  The date to format.
		 *
		 * @return 	string 	 	 The formatted date.
		 */
		static getFormattedDate(dt) {
			// convert string to date
			if (typeof dt === 'string') {
				dt = DateHelper.stringToDate(dt);
			}

			// format locale date
			return dt.toLocaleDateString();
		}

		/**
		 * Formats the specified time according to the browser locale.
		 *
		 * @param 	string|Date  dt  The date to format.
		 *
		 * @return 	string 	 	 The formatted time.
		 */
		static getFormattedTime(dt) {
			// convert string to date
			if (typeof dt === 'string') {
				dt = DateHelper.stringToDate(dt);
			}

			// format locale time (no seconds)
			return dt.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
		}

		/**
		 * Converts the specified date into a valid SQL (UTC) date format.
		 *
		 * @param 	string|Date  dt  The date to format.
		 *
		 * @return 	string 	 	 The resulting date string.
		 */
		static toStringUTC(dt) {
			// convert string to date
			if (typeof dt === 'string') {
				dt = DateHelper.stringToDate(dt);
			}

			var year  = dt.getUTCFullYear();
			var month = dt.getUTCMonth() + 1;
			var day   = dt.getUTCDate();
			var hour  = dt.getUTCHours();
			var min   = dt.getUTCMinutes();
			var sec   = dt.getUTCSeconds();

			var date = year + '-' + (month < 10 ? '0' : '') + month + '-' + (day < 10 ? '0' : '') + day;
			var time = (hour < 10 ? '0' : '') + hour + ':' + (min < 10 ? '0' : '') + min + ':' + (sec < 10 ? '0' : '') + sec;

			return date + ' ' + time;
		}

		/**
		 * Converts the specified date string into a Date object.
		 *
		 * @param 	string  str  The date to format.
		 *
		 * @return 	Date 	The date object.
		 */
		static stringToDate(str) {
			return new Date(str.replace(/\s+/, 'T'));
		}

	}

	/**
	 * Sort the children of the attached content by
	 * using a visual animation.
	 *
	 * @property  function  callback 	The callback function used to sort the elements.
	 * 									The function receives the 2 elements to compare and
	 * 									must return -1 in case the first one is lower,
	 * 									1 if higher or 0 if they are equals.
	 * @property  string 	column 		In case the callback is not provided, the column
	 * 									can be used to use a default sort by taking the
	 * 									specified attribute.
	 * @property  string 	direction 	The ordering direction (asc or desc). ASC by default.
	 * 									Direction is never used in case callback has been provided.
	 * @property  string 	filter 		A mask used to filter the values to compare (string by default).
	 * 									Filter is never used in case callback has been provided.
	 * @property  integer	duration 	The duration of the sorting animation (in milliseconds).
	 * 									If not provided, the animation will last 500 ms.
	 *
	 * @return 	  self
	 */
	$.fn.animatedSort = function(data) {
		// create helper function used to attach the animation to the specified
		// element by using the given duration and top position
		function attachAnimation(elem, duration, top) {
			$(elem).css('-webkit-transition', '-webkit-transform ' + (duration / 1000.0) + 's ease')
				.css('-moz-transition', '-moz-transform ' + (duration / 1000.0) + 's ease')
				.css('-o-transition', '-o-transform ' + (duration / 1000.0) + 's ease')
				.css('transition', 'transform ' + (duration / 1000.0) + 's ease')
				.css('transform', 'translateY(' + top + 'px)');
		};

		// create helper function used to detach the animation from the
		// specified element
		function detachAnimation(elem) {
			$(elem).css('-webkit-transition', 'none')
				.css('-moz-transition', 'none')
				.css('-o-transition', 'none')
				.css('transition', 'none')
				.css('transform', 'none');
		};

		// check if we have a valid callback
		if (typeof data.callback !== 'function') {
			// define the compare callback
			data.callback = function(a, b) {
				// get values to compare
				var x = $(a).attr(data.column);
				var y = $(b).attr(data.column);

				if (data.filter === 'int' || data.filter === 'integer' || data.filter === 'numeric') {
					// filter values to compare integers
					x = parseInt(x);
					y = parseInt(y);
				} else if (data.filter === 'float' || data.filter === 'double') {
					// filter values to compare floats
					x = parseFloat(x);
					y = parseFloat(y);
				}

				// equals by default
				var delta = 0;

				if (x < y) {
					// A is lower than B
					delta = -1;
				} else if (x > y) {
					// A is highet than B
					delta = 1;
				}

				// in case of DESC direction, we need to revert delta value
				if (data.direction && data.direction.toLowerCase() === 'desc') {
					delta *= -1;
				}

				return delta;
			};
		}

		// sort element children using our callback
		var r = $(this).children().sort(data.callback);

		// create the animation pool if undefined
		if ($.sortAnimationTimer === undefined) {
			$.sortAnimationTimer = {};
		}

		// get element ID
		var id = $(this)[0].id ? $(this)[0].id : 'none';

		// check if we are currently running an animation for this element
		if ($.sortAnimationTimer.hasOwnProperty(id)) {
			// clear animation timeout
			clearTimeout($.sortAnimationTimer[id]);

			// iterate children and clear current transformation
			$(this).children().each(function(k, v) {
				// detach animation from the element
				detachAnimation(v);
			});
		}

		var newY = [];

		var _this = this;

		// define default duration if not provided
		data.duration = data.duration ? data.duration : 500;

		// iterate children to calculate the new position they should own
		$(this).children().each(function(k, v) {
			// find the position that this element should have
			// using the requested ordering
			var sortedIndex = $(r).index(v);
			// get the element at the position found from the unordered list
			var tmp = $(_this).children().eq(sortedIndex);

			// calculate difference between the TOP offset of the current item
			// and the temporary element we found
			newY.push($(tmp).offset().top - $(v).offset().top);
		});

		// iterate children to apply animation
		$(this).children().each(function(k, v) {
			// attach animation to the element
			attachAnimation(v, data.duration, newY[k]);
		});

		// define timeout to apply the new ordering once the animation is finished
		$.sortAnimationTimer[id] = setTimeout(function() {
			// appendTo is used to replace the current list with the sorted
			// one without breaking attached events
			$(r).appendTo(_this).each(function(k, v) {
				// detach animation from the element
				detachAnimation(v);
			});

			// clear animation token
			delete $.sortAnimationTimer[id];
		}, data.duration);

		return this;
	}


	/**
	 * UIAjax class.
	 * Handles asynch server-side connections.
	 */
	w['UIAjax'] = class UIAjax {
		
		/**
		 * Normalizes the given argument to be sent via AJAX.
		 *
		 * @param 	mixed 	data  An object, an associative array or a serialized string.
		 *
		 * @return 	object 	The normalized object.
		 */
		static normalizePostData(data) {

			if (data === undefined) {
				data = {};
			} else if (Array.isArray(data)) {
				// the form data is serialized @see $.serializeArray()
				var form = data;

				data = {};

				for (var i = 0; i < form.length; i++) {
					// if the field ends with [] it should be an array
					if (form[i].name.endsWith("[]")) {
						// if the field doesn't exist yet, create a new list
						if (!data.hasOwnProperty(form[i].name)) {
							data[form[i].name] = new Array();
						}

						// append the value to the array
						data[form[i].name].push(form[i].value);
					} else {
						// otherwise overwrite the value (if any)
						data[form[i].name] = form[i].value;
					}
				}
			}

			return data;
		}

		/**
		 * Makes the connection.
		 *
		 * @param 	mixed 	  url 		The URL to reach or a configuration object.
		 * @param 	mixed 	  data 		The data to post.
		 * @param 	function  success 	The callback to invoke on success.
		 * @param 	function  failure 	The callback to invoke on failure.
		 * @param 	integer   attempt 	The current attempt (optional).
		 *
		 * @return 	void
		 */
		static do(url, data, success, failure, attempt) {

			if (attempt == 1 || attempt === undefined) {
				if (!UIAjax.concurrent && UIAjax.isDoing()) {
					return false;
				}
			}

			if (attempt === undefined) {
				attempt = 1;
			}

			// return same object if data has been already normalized
			data = UIAjax.normalizePostData(data);

			var config = {};

			if (typeof url === 'object') {
				// we have a configuration object, use it
				Object.assign(config, url);
			} else {
				// use the default configuration
				config.type = 'post';
				config.url  = url;
			}

			// inject data within config
			config.data = data;

			var xhr = $.ajax(
				// use fetched config
				config
			).done(function(resp) {

				UIAjax.pop(xhr);

				if (success !== undefined) {
					// check if we should wait for another call
					if (UIAjax.isRunningProcess(xhr.idAfter)) {
						// register promise
						UIAjax.registerPromise(xhr.idAfter, success, resp);
					} else {
						// execute callback directly
						success(resp);
					}
				}

				// process pending promises
				UIAjax.processPromises(xhr.identify());

			}).fail(function(err) {
				// always pop XHR after failure
				UIAjax.pop(xhr);

				// If the error has been raised by a connection failure, 
				// retry automatically the same request. Do not retry if the
				// number of attempts is higher than the maximum number allowed.
				if (attempt < UIAjax.maxAttempts && UIAjax.isConnectionLost(err)) {

					// wait 256 milliseconds before launching the request
					setTimeout(function() {
						// relaunch same action and increase number of attempts by 1
						UIAjax.do(url, data, success, failure, attempt + 1);
					}, 256);

				} else {

					// otherwise raise the failure method
					if (failure !== undefined) {
						failure(err);
					}

				}

				console.error(err);

				if (err.status.toString().match(/^5[\d]{2,2}$/)) {
					console.error(err.responseText);
				}

			});

			UIAjax.push(xhr);

			return xhr;
		}
		/**
		 * Makes the connection with the server and start uploading the given data.
		 *
		 * @param 	string 	  url 		The URL to reach.
		 * @param 	mixed 	  data 		The data to upload.
		 * @param 	function  done 		The callback to invoke on success.
		 * @param 	function  failure 	The callback to invoke on failure.
		 * @param 	function  upload 	The callback to invoke to track the uploading progress.
		 *
		 * @return 	void
		 */
		static upload(url, data, done, failure, upload) {
			// define upload config
			var config = {
				url: 		 url,
				type: 		 "post",
				contentType: false,
				processData: false,
				cache: 		 false,
			};

			// define upload callback to keep track of progress
			if (typeof upload === 'function') {
				config.xhr = function() {
					var xhrobj = $.ajaxSettings.xhr();

					if (xhrobj.upload) {
						// attach progress event
						xhrobj.upload.addEventListener('progress', function(event) {
							// calculate percentage
							var percent  = 0;
							var position = event.loaded || event.position;
							var total 	 = event.total;
							if (event.lengthComputable) {
								percent = Math.ceil(position / total * 100);
							}

							// trigger callback
							upload(percent);
						}, false);
					}

					return xhrobj;
				};
			}

			// invoke default do() method by using custom config
			return UIAjax.do(config, data, done, failure);
		}

		/**
		 * Checks if we own at least an active connection.
		 *
		 * @return 	boolean
		 */
		static isDoing() {
			return UIAjax.stack.length > 0 && UIAjax.count > 0;
		}

		/**
		 * Checks if the process with the specified ID is running.
		 *
		 * @param 	mixed 	 id  The process identifier.
		 *
		 * @return 	boolean  True if the process is running, false otherwise.
		 */
		static isRunningProcess(id) {
			if (!id) {
				return false;
			}

			// iterate the stack
			for (var i = 0; i < UIAjax.stack.length; i++) {
				// get XHR instance
				var xhr = UIAjax.stack[i];

				if (typeof xhr === 'object' && xhr.identifier === id) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Checks if we are currently running a critical XHR.
		 * XHRs can be marked in that way by using the prototyped
		 * critical() function.
		 *
		 * @return 	boolean  True if there is at least a critical XHR, otherwise false. 
		 */
		static isRunningCritical() {
			// iterate the stack
			for (var i = 0; i < UIAjax.stack.length; i++) {
				// get XHR instance
				var xhr = UIAjax.stack[i];

				if (typeof xhr === 'object' && typeof xhr.isCritical === 'function' && xhr.isCritical()) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Registers a new promise for the specified identifier.
		 *
		 * @param 	mixed 	  id 		The identifier to check.
		 * @param 	function  callback  The callback to trigger.
		 * @param 	mixed 	  args 	    The argument of the callback.
		 *
		 * @return 	void
		 */
		static registerPromise(id, callback, args) {
			if (!id) {
				return;
			}

			if (!UIAjax.promises.hasOwnProperty(id)) {
				// create list
				UIAjax.promises[id] = [];
			}

			// register promise
			UIAjax.promises[id].push({
				callback: callback,
				args: 	  args,
			});
		}

		/**
		 * Processes all the pending promises for the specified ID.
		 *
		 * @param 	mixed 	id 	The id to check.
		 *
		 * @return 	void
		 */
		static processPromises(id) {	
			if (!UIAjax.promises.hasOwnProperty(id)) {
				return
			}

			// iterate promises lists
			while (UIAjax.promises[id].length) {
				// get first callback available
				var tmp = UIAjax.promises[id].shift();

				// trigger callback
				tmp.callback(tmp.args);
			}
		}

		/**
		 * Pushes the opened connection within the stack.
		 *
		 * @param 	mixed 	xhr  The connection resource.
		 *
		 * @return 	void
		 */
		static push(xhr) {
			UIAjax.stack.push(xhr);
			UIAjax.count++;
		}

		/**
		 * Removes the specified connection from the stack.
		 *
		 * @param 	mixed 	xhr  The connection resource.
		 *
		 * @return 	void
		 */
		static pop(xhr) {
			var index = UIAjax.stack.indexOf(xhr);

			if (index != -1) {
				UIAjax.stack.splice(index, 1);
			}

			UIAjax.count--;
		}

		/**
		 * Checks if the given error can be intended as a loss of connection:
		 * generic error, no status and no response text.
		 * 
		 * @param 	object 	err 	The error object.
		 *
		 * @return 	boolean
		 */
		static isConnectionLost(err) {
			return (
				err.statusText == 'error'
				&& err.status == 0
				&& err.readyState == 0
				&& err.responseText == ''
			);
		}
	}

	UIAjax.stack 		= [];
	UIAjax.promises 	= {};
	UIAjax.count 		= 0;
	UIAjax.concurrent 	= true;
	UIAjax.maxAttempts 	= 5;

	/**
	 * Checks if the specified elements are equal.
	 *
	 * @param 	mixed 	x
	 * @param 	mixed 	y
	 *
	 * @return 	boolean  True if identical, false otherwise. 
	 */
	Object.equals = function(x, y) {
		// if both x and y are null or undefined and exactly the same
		if (x === y)
			return true;

		// if they are not strictly equal, they both need to be Objects
		if (!(x instanceof Object) || !(y instanceof Object))
			return false;

		// they must have the exact same prototype chain, the closest we can do is
		// test there constructor
		if (x.constructor !== y.constructor)
			return false;

		for (var p in x) {
			// make sure we are testing a valid property
			if (!x.hasOwnProperty(p))
				continue;

			// allows to compare x[p] and y[p] when set to undefined
			if (!y.hasOwnProperty(p))
				return false;

			// if they have the same strict value or identity then they are equal
			if (x[p] === y[p])
				continue;

			// Numbers, Strings, Functions, Booleans must be strictly equal
			if (typeof(x[p]) !== "object")
				return false;

			// Objects and Arrays must be tested recursively
			if (!Object.equals(x[p],  y[p]))
				return false;
		}

		for (p in y) {
			// allows x[p] to be set to undefined
			if (y.hasOwnProperty(p) && !x.hasOwnProperty(p))
				return false;
		}

		return true;
	}

	/**
	 * Converts the most common special chars in their HTML entities.
	 *
	 * @return 	string 	The converted string.
	 */
	String.prototype.htmlentities = function() {
		return this.toString()
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/**
	 * Escapes single quotes and double quotes by converting
	 * them in the related HTML entity.
	 *
	 * @return 	string 	The escaped string
	 */
	String.prototype.escape = function() {
		return this.toString().replace(/"/g, '&quot;');
	}

	/**
	 * Whenever an Ajax request is about to be sent, jQuery triggers the ajaxSend event.
	 * Any and all handlers that have been registered with the .ajaxSend() method are executed at this time.
	 * We can implement here all the methods that jqXHR object should support.
	 */
	$(window).ajaxSend(function(event, xhr, settings) {
		/**
		 * Marks the jqXHR object as critical according to the specified argument.
		 *
		 * @param 	mixed 	is 	Whether the XHR is critical or not. Undefined
		 * 						argument is assumed as TRUE.
		 *
		 * @return 	self
		 */
		xhr.critical = function(is) {
			this.criticalFlag = (is === undefined ? true : is);

			return this;
		};

		/**
		 * Checks whether the jqXHR object is critical or not.
		 *
		 * @return 	boolean
		 */
		xhr.isCritical = function(is) {
			return this.criticalFlag ? true : false;
		};

		/**
		 * Sets/Gets the ID of the jqXHR object.
		 *
		 * @param 	mixed 	id 	The identifier to set.
		 *
		 * @return 	mixed 	The identifier.
		 */
		xhr.identify = function(id) {
			if (id !== undefined) {
				this.identifier = id;
			}

			return this.identifier;
		}

		/**
		 * This method is used to push the callback of the request
		 * in a queue to be executed once [id] request has finished.
		 *
		 * @param 	mixed 	id 	The identifier of the process to observe.
		 *
		 * @return 	self
		 */
		xhr.after = function(id) {
			this.idAfter = id;

			return this;
		}
	});

	/**
	 * The beforeunload event is fired when the window, the document and its resources are about to be unloaded.
	 * The document is still visible and the event is still cancelable at this point.
	 *
	 * If a string is assigned to the returnValue Event property, a dialog appears asking the user for confirmation 
	 * to leave the page (see example below). Some browsers display the returned string in the dialog box, but others
	 * display their own message. If no value is provided, the event is processed silently.
	 */
	$(window).on('beforeunload', function(event) {
		// check if we are running a XHR in background
		// that shouldn't be aborted
		if (UIAjax.isRunningCritical()) {
			// cancel the event and prompt the confirmation alert
			event.preventDefault();
			// for some browsers it is needed to setup a return value
			event.returnValue = 'Do you want to leave the page?';
		}
	});
})(jQuery, window);