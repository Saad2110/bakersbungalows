<?php
/**
 * @package     VikBooking
 * @subpackage  com_vikbooking
 * @author      Alessio Gaggii - e4j - Extensionsforjoomla.com
 * @copyright   Copyright (C) 2018 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

class VikCronJob
{	
	/**
	 * Defines the parameters of the cron job.
	 * 
	 * @return 	array
	 */
	public static function getAdminParameters()
	{
		return [
			'cron_lbl' => [
				'type'  => 'custom',
				'label' => '',
				'html'  => '<h4><i class="far fa-file-archive"></i>&nbsp;<i class="far fa-clock"></i>&nbsp;Backup Creator</h4>',
			],
			'maxbackup' => [
				'type'    => 'number',
				'label'   => JText::_('VBO_CRONJOB_BACKUP_CREATOR_FIELD_MAX') . '//' . JText::_('VBO_CRONJOB_BACKUP_CREATOR_FIELD_MAX_DESC'),
				'min'     => 0,
				'step'    => 1,
				'default' => 5,
			],
			'help' => [
				'type'  => 'custom',
				'label' => '',
				'html'  => '<p class="vbo-cronparam-suggestion"><i class="vboicn-lifebuoy"></i>' . JText::_('VBO_CRONJOB_BACKUP_CREATOR_DESCRIPTION') . '</p>',
			],
		];
	}
	
	public function __construct($cron_id, $params = array())
	{
		$this->cron_id = $cron_id;
		$this->params  = $params;
		$this->log     = '';
	}
	
	public function run()
	{
		// create backup model
		$model = new VBOModelBackup();

		try
		{
			// create back-up
			$archive = $model->save([
				'action' => 'create',
				'prefix' => 'cron_',
			]);

			if (!$archive)
			{
				// an error occurred while creating the backup archive
				throw new Exception($model->getError($index = null, $string = true), 500);
			}

			// register response
			$this->log .= "Back-up created successfully!\n\n<b>{$archive}</b>";
		}
		catch (Exception $e)
		{
			// an error occurred while creating the back-up
			$this->log .= '<p style="color: #900;">' . $e->getMessage() . '</p>';
			
			return false;
		}

		// get list of created archives
		$files = JFolder::files(dirname($archive), '^cron_backup_', $recursive = false, $fullpath = true);

		// check whether the number of created backups exceeded the maximum threshold
		$diff = count($files) - max([1, abs(@$this->params['maxbackup'])]);
		
		if ($diff > 0)
		{
			// sort the files by ascending creation date
			usort($files, function($a, $b)
			{
				return filemtime($a) - filemtime($b);
			});

			// take the first N archives to delete
			foreach (array_splice($files, 0, $diff) as $file)
			{
				// delete the files
				if (JFile::delete($file))
				{
					$this->log .= "\n\nDeleted old archive: <b>" . basename($file) . '</b>';
				}
			}
		}

		return true;
	}
	
	public function afterRun($extra = array())
	{
		$dbo = JFactory::getDbo();

		$q = $dbo->getQuery(true)
			->update($dbo->qn('#__vikbooking_cronjobs'))
			->set($dbo->qn('last_exec') . ' = ' . time())
			->where($dbo->qn('id') . ' = ' . (int) $this->cron_id);

		if ($this->log)
		{
			$this->log .= "\n\n";

			$q->set(sprintf(
				'%1$s = CONCAT(%2$s, \'<hr />\n\', IFNULL(%1$s, \'\'))',
				$dbo->qn('logs'),
				$dbo->q($this->log)
			));
		}

		$dbo->setQuery($q);
		$dbo->execute();
	}
}
