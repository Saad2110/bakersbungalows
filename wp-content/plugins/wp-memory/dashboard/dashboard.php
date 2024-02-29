<?php
/**
 * @ Author: Bill Minozzi
 * @ Copyright: 2020 www.BillMinozzi.com
 * @ Modified time: 2021-03-03 09:07:38
 */
if (!defined("ABSPATH")) {
    die('We\'re sorry, but you can not directly access this file.');
}
global $wpmemory_memory;
//display form
echo '<div class="wrap-wpmemory ">' . "\n";
echo '<h2 class="title">PHP and WordPress Memory</h2>' . "\n";
echo '<p class="description">' .
    esc_attr__(
        "This plugin check For High Memory Usage and also include the result in the Tools => Site Health Page. Our Premium version can modify the necessary files to increase your WP Memory Limit and PHP Memory without risking accidental file modifications.",
        "wp-memory"
    );
// echo esc_attr__("This plugin also Check Memory status and allows you to increase the Php Memory Limit and WordPress Memory Limit without editing any file.","wp-memory").'</p>' . "\n";

/////////////////

echo "<center><h2>" . esc_attr__("Memory Usage", "wp-memory") . "</h2>";
$ds = 256;
$du = 60;
$ds = $wpmemory_memory["wp_limit"];
$du = $wpmemory_memory["usage"];
if ($ds > 0) {
    $perc = number_format((100 * $du) / $ds, 0);
} else {
    $perc = 0;
}
if ($perc > 100) {
    $perc = 100;
}
//die($perc);
$color = "#e87d7d";
$color = "#029E26";
if ($perc > 50) {
    $color = "#e8cf7d";
}
if ($perc > 70) {
    $color = "#ace97c";
}
if ($perc > 50) {
    $color = "#F7D301";
}
if ($perc > 70) {
    $color = "#ff0000";
}
$initValue = $perc;

require_once "circle_memory.php";

/////////////////////

$mb = "MB";
echo "<br />";
echo "<hr>";
echo "<b>";
echo "WordPress Memory Limit (*): " .
    esc_attr($wpmemory_memory["wp_limit"]) .
    esc_attr($mb) .
    "&nbsp;&nbsp;&nbsp;  |&nbsp;&nbsp;&nbsp;";
$perc = $wpmemory_memory["usage"] / $wpmemory_memory["wp_limit"];
if ($perc > 0.7) {
    echo '<span style="color:' . esc_attr($wpmemory_memory["color"]) . ';">';
}
echo esc_attr__("Your usage now", "wp-memory") .
    ": " .
    esc_attr($wpmemory_memory["usage"]) .
    "MB &nbsp;&nbsp;&nbsp;";
if ($perc > 0.7) {
    echo "</span>";
}
echo "|&nbsp;&nbsp;&nbsp;" .
    esc_attr__("PHP Memory", "wp-memory") .
    " (**): " .
    esc_attr($wpmemory_memory["limit"]) .
    "MB";
echo "</b>";
echo "</center>";
echo "<hr>";

$free = $wpmemory_memory["wp_limit"] - $wpmemory_memory["usage"];

if ($perc > 0.7 or $free < 30) {
    echo '<h2 style="color: red;">';
    echo esc_attr__(
        "Our plugin cannot function properly because your WordPress memory limit is too low. Your site will experience serious issues, even if you deactivate our plugin.",
        "wpmemory"
    );
    // echo $free;
    echo "</h2>";
}

echo '<div style="font-size: 14px;">';

echo "<br />";

echo '<p class="description">' .
    esc_attr__("Understanding Memory Usage in WordPress.", "wp-memory");

echo "</p>";
echo esc_attr__(
    "To comprehend the entire process of memory usage in WordPress, you need to grasp three key points:",
    "wp-memory"
);
echo "<br />";
echo "<br />";
echo "<strong>";
echo esc_attr__("1) Total server memory (HARDWARE MEMORY):", "wp-memory");
echo "</strong>";
echo "<br />";
echo esc_attr__(
    "Server memory refers to the total physical memory of your server and can only be increased through physical intervention, which should be requested from your hosting provider. Look the TAB Hardware Memory above.",
    "wp-memory"
);
echo "<br />";
echo "<br />";
echo "<strong>";
echo esc_attr__("2) PHP Memory**:", "wp-memory");
echo "</strong>";
echo "<br />";
echo esc_attr__(
    "PHP Memory is usually defined in the php.ini file (the default configuration file) located outside your WordPress environment. It must be lower than the PHP memory (point 1).",
    "wp-memory"
);
echo "<br />";
echo "(**)" . esc_attr__("Instructions to increase PHP Memory:", "wp-memory");
echo '<a href="https://wpmemory.com/php-memory-limit/">';
echo esc_attr__("Click Here to learn more", "wp-memory");
echo "</a>";
echo "<br />";
echo "<br />";

echo "<strong>";
echo esc_attr__("3) WordPress Memory Limit*:", "wp-memory");
echo "</strong>";
echo "<br />";
echo esc_attr__(
    "WP Memory Limit is the maximum limit WordPress allows for each user and script of your site.",
    "wp-memory"
);    
    
 //   It must be lower than the PHP memory (point 2).",
 //   "wp-memory"
//);
echo ' ';
if($wpmemory_memory["wp_limit"] > $wpmemory_memory["limit"]  ){

    echo '<span style="color: red;">' . esc_attr__(
        "It must be lower than the PHP memory (point 2).",
        "wp-memory"
    ) . '</span>';
    
}
else {

    echo esc_attr__(
        "It must be lower than the PHP memory (point 2).",
        "wp-memory"
    );


}



echo "<br />";
echo "(*)" .
    esc_attr__("Instructions to increase WP Memory Limit:", "wp-memory");
echo '<a href="https://wpmemory.com/fix-low-memory-limit/">';
echo esc_attr__("Click Here to learn more", "wp-memory");
echo "</a>";

echo "<br />";
echo "<br />";

echo "<br />";
echo "<b> ";
echo esc_attr__(
    "How to Tell if Your Site Needs a Shot of more Memory",
    "wp-memory"
);

echo "</b> ";

echo "<br />";
echo "<br />";

echo esc_attr__("If you got", "wp-memory");
echo "<i> ";

echo esc_attr__(
    "Fatal error: Allowed memory size of xxx bytes exhausted",
    "wp-memory"
);

echo "</i> ";
echo esc_attr__("or", "wp-memory");
echo "<br>";

echo esc_attr__(
    "if your site is behaving slowly, or pages fail to load, you get random white screens of death or 500 internal server error you may need more memory. Several things consume memory, such as WordPress itself, the plugins installed, the theme you're using and the site content.",
    "wp-memory"
);

echo "<br> ";
echo esc_attr__(
    "Basically, the more content and features you add to your site, the bigger your memory limit has to be. if you're only running a small site with basic functions without a Page Builder and Theme Options (for example the native Twenty twenty) maybe you don’t need make memory adjustments. However, once you use a Premium WordPress theme and you start encountering unexpected issues, it may be time to adjust your memory limit to meet the standards for a modern WordPress installation.",
    "wp-memory"
);

echo "<br> ";
echo "<br> ";
echo esc_attr__(
    "Increase the WP Memory Limit is a standard practice in WordPress and you find instructions also in the official WordPress documentation (Increasing memory allocated to PHP).",
    "wp-memory"
);

echo "<br>";

esc_attr_e(
    "Visit the plugin site for more details, video, FAQ and Troubleshooting page.",
    "wp-memory"
);
echo "<br>";
echo "<br>";
echo '<a href="https://wpmemory.com/" class="button button-primary">' .
    esc_attr__("Plugin Site", "wp-memory") .
    "</a>";
echo "&nbsp;&nbsp;";
echo '<a href="https://wpmemory.com/help/" class="button button-primary">' .
    esc_attr__("Online Guide", "wp-memory") .
    "</a>";
echo "&nbsp;&nbsp;";
echo '<a href="https://billminozzi.com/dove/" class="button button-primary">' .
    esc_attr__("Support Page", "wp-memory") .
    "</a>";
echo "&nbsp;&nbsp;";
echo '<a href="https://siterightaway.net/troubleshooting/" class="button button-primary">' .
    esc_attr__("Troubleshooting Page", "wp-memory") .
    "</a>";
echo "<br>";
echo "<br>";
echo "</div>";

echo "</div>";
