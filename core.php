<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/php/secret_config.php');

// ============================================================================
// Constants & Utils
// ============================================================================

$WORKING_LOCALLY = substr($_SERVER['DOCUMENT_ROOT'], 0, 3) == "C:/" || substr($_SERVER['DOCUMENT_ROOT'], 0, 3) == "X:/";

$SYSTEM_ROOT = $_SERVER['DOCUMENT_ROOT'];
if ($WORKING_LOCALLY){
    $SYSTEM_ROOT = $GLOBALS['LOCAL_WORKING_FOLDER'];
}

require_once($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');
use Patreon\API;
use Patreon\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$patreon = get_patreon();
$PATRON_LIST = $patreon[0];
$PATREON_GOALS = $patreon[2];
$PATREON_CURRENT_GOAL = null;
foreach ($PATREON_GOALS as $g){
    $PATREON_CURRENT_GOAL = $g;
    if ($g['completed_percentage'] < 100 && strpos($g['description'], "[reached") == false){
        break;
    }

}
if ($PATREON_CURRENT_GOAL['completed_percentage'] < 100){
    $PATREON_EARNINGS = floor(($PATREON_CURRENT_GOAL['amount_cents']*($PATREON_CURRENT_GOAL['completed_percentage']/100))/100);
}else{
    $PATREON_EARNINGS = $patreon[1];
}

// Don't cache these pages | GET params ignored | matched to $_SERVER['PHP_SELF']
$NO_CACHE = ["/gallery/do_submit.php",
             "/gallery/moderate.php"
            ];

function nice_name($name, $mode="normal"){
    $str = str_replace('_', ' ', $name);
    if ($mode=="category"){
        // Some categories have a slash in them, but that would ruin URLs so they are stored as a dash instead and then replaced with a slash for display
        $str = implode('/', array_map('ucfirst', explode('-', $str)));
    }
    $str = ucwords($str);
    return $str;
}

function to_slug($name){
    $name = str_replace(' ', '_', $name);
    $name = strtolower($name);
    $name = simple_chars_only($name);
    return $name;
}

function md_to_html($s){
    require_once($_SERVER['DOCUMENT_ROOT'].'/core/parsedown/Parsedown.php');
    $parsedown = new Parsedown();
    return $parsedown->text($s);
}

function starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function ends_with($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

function str_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

function str_lreplace($search, $replace, $subject) {
    // Replace only last occurance in string
    $pos = strrpos($subject, $search);

    if($pos !== false)
    {
        $subject = substr_replace($subject, $replace, $pos, strlen($search));
    }

    return $subject;
}

function filepath_to_url($fp){
    $fp = str_replace($GLOBALS['SYSTEM_ROOT'], "/", $fp);
    return str_replace("//", "/", $fp);
}

function url_to_filepath($url){
    $url = str_replace($GLOBALS['SITE_URL'], "", $url);
    $url = $GLOBALS['SYSTEM_ROOT'].$url;
    return str_replace("//", "/", $url);
}

function content_hashed_url($url){
    echo $url."?v=".substr(hash_file("md5", url_to_filepath($url)), 0, 6);
}

function fmoney($i){
    return number_format($i, 2, '.', ' ');
}

function random_hash($length=8){
    $chars = "qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM0123456789";
    $hash = "";
    for ($i=0; $i<$length; $i++){
        $hash .= $chars[rand(0, strlen($chars)-1)];
    }
    return $hash;
}

function simple_hash($str){
    // Simple not-so-secure 8-char hash
    return hash('crc32', $GLOBALS['GEN_HASH_SALT'].$str, FALSE);
}

function simple_chars_only($str){
    return preg_replace("/[^A-Za-z0-9_\- ]/", '', $str);
}

function numbers_only($str){
    return preg_replace("/[^0-9]/", '', $str);
}

function map_range($value, $fromLow, $fromHigh, $toLow, $toHigh) {
    $fromRange = $fromHigh - $fromLow;
    $toRange = $toHigh - $toLow;
    $scaleFactor = $toRange / $fromRange;

    $tmpValue = $value - $fromLow;
    $tmpValue *= $scaleFactor;
    return $tmpValue + $toLow;
}

function human_filesize($size) {
    // From https://gist.github.com/liunian/9338301#gistcomment-1970620
    for($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {}
    return round($size, [0,0,1,2,2,3,3,4,4][$i]).' '.['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
}

function time_ago($strtime) {
    // Source: http://goo.gl/LQJWnW

    $time = time() - strtotime($strtime); // to get the time since that moment

    $tokens = array (
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
    );

    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = round($time / $unit);
        $rstr = $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'')." ago";
        if ($text == 'day'){
            $rstr = "<span class='new-tex'>".$rstr."</span>";
        }
        return $rstr;
    }
    return "<span class='new-".$GLOBALS['CONTENT_TYPE_SHORT']."'>Today</span>";
}

function is_in_the_past($d) {
    return (time() - strtotime($d) > 0);
}

function first_in_array($a){
    // Return first item of array, php is silly
    $a = array_reverse($a);
    return array_pop($a);
}

function array_sort($array, $on, $order=SORT_ASC){

    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
                break;
            case SORT_DESC:
                arsort($sortable_array);
                break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}

function resize_image($old_fp, $new_fp, $format, $size_x, $size_y, $quality=85){
    $img = new imagick($old_fp);
    $img->resizeImage($size_x, $size_y, imagick::FILTER_BOX, 1, true);
    $img->setImageFormat($format);
    if ($format == "jpg"){
        $img->setImageCompression(Imagick::COMPRESSION_JPEG);
        $img->setImageCompressionQuality($quality);
    }
    $img->writeImage($new_fp);
}

function send_email($to_email, $subject, $message){
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = $GLOBALS['EMAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $GLOBALS['EMAIL_USER'];
        $mail->Password   = $GLOBALS['EMAIL_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        //Recipients
        $mail->setFrom($GLOBALS['EMAIL_FROM'], $GLOBALS['EMAIL_FROM_NAME']);
        $mail->addAddress($to_email);
        $mail->addReplyTo($GLOBALS['EMAIL_FROM'], $GLOBALS['EMAIL_FROM_NAME']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
    } catch (Exception $e) {
        echo "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function debug_email($subject, $text){
    send_email($GLOBALS['ADMIN_EMAIL'], $subject, $text);
}

function debug_console($str){
    echo "<script>";
    echo "console.log(\"".$str."\");";
    echo "</script>";
}

function print_ra($array){
    echo "<pre>";
    print_r($array);
    echo "</pre>";
}

function join_paths() {
    $paths = array();
    foreach (func_get_args() as $arg) {
        if ($arg !== '') { $paths[] = $arg; }
    }
    return preg_replace('#/+#','/',join('/', $paths));
}

function listdir($d, $mode="ALL"){
    // List contents of folder, without hidden files
    $sd = scandir($d);
    natcasesort($sd);
    $files = [];
    foreach ($sd as $f){
        if (!starts_with($f, '.')){
            $is_file = str_contains($f, '.');  // is_dir doesn't work reliably on windows, so we assume all folders do not contain '.' #YOLO
            if (($mode == "ALL") or ($mode == "FOLDERS" and !$is_file) or ($mode == "FILES" and $is_file)){
                array_push($files, $f);
            }
        }
    }
    return $files;
}

function qmkdir($d) {
    // Quitly mkdir if it doesn't exist aleady, recursively
    if (!file_exists($d)){
        mkdir($d, 0777, true);
    }
}

function is_image_file($fp){
    if(@is_array(getimagesize($fp))){
        return true;
    } else {
        return false;
    }
}

function clear_cache(){
    $cache_dir = $_SERVER['DOCUMENT_ROOT']."/php/cache/";
    $r = array_map('unlink', glob("$cache_dir*.html"));
    return sizeof($r);  // Number of cache files cleared
}


// ============================================================================
// HTML
// ============================================================================

function include_start_html($title, $slug="", $canonical="", $t1="") {
    ob_start();
    include $_SERVER['DOCUMENT_ROOT']."/php/html/start_html.php";
    $html = ob_get_contents();
    $textures_one = "";
    ob_end_clean();

    if ($title == "Render Gallery" || $slug != ""){
        $html = str_replace('%GALLERYJS%', "<link rel=\"stylesheet\" href=\"/js/flexImages/jquery.flex-images.css\"><script src=\"/js/flexImages/jquery.flex-images.min.js\"></script>", $html);
    }else{
        $html = str_replace('%GALLERYJS%', "", $html);
    }

    if ($title == "Map"){
        $html = str_replace('%MAP%', "<link rel='stylesheet' href='https://unpkg.com/leaflet@1.6.0/dist/leaflet.css'
        integrity='sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=='
        crossorigin=''/>
        <script src='https://unpkg.com/leaflet@1.6.0/dist/leaflet.js'
        integrity='sha512-gZwIG9x3wUXg2hdXF6+rVkLF/0Vi9U8D2Ntg4Ga5I5BZpVkVxlJWbSQtXPSiUTtC0TjtGOmxa1AJPuV0CPthew=='
        crossorigin=''></script>", $html);
    }else{
        $html = str_replace('%MAP%', "", $html);
    }

    if ($title != $GLOBALS['SITE_NAME']){
        $title .= " | ".$GLOBALS['SITE_NAME'];
        $html = str_replace('%LANDINGJS%', "", $html);
    }else{
        $html = str_replace('%LANDINGJS%', "<script src='/js/landing-slider.js'></script>", $html);
        $textures_one = "<!-- START Textures.one integration -->
        <meta name=\"tex1:display-name\" content=\"".$GLOBALS['SITE_NAME']."\" />
        <meta name=\"tex1:display-domain\" content=\"".$GLOBALS['SITE_DOMAIN']."\" />
        <meta name=\"tex1:patreon\" content=\"".$GLOBALS['HANDLE_PATREON']."\" />
        <meta name=\"tex1:twitter\" content=\"".$GLOBALS['HANDLE_TWITTER']."\" />
        <meta name=\"tex1:instagram\" content=\"\" />
        <meta name=\"tex1:logo\" content=\"".$GLOBALS['SITE_LOGO_URL']."\" />
        <meta name=\"tex1:icon\" content=\"".$GLOBALS['SITE_URL']."/favicon.png\" />
        <!-- END Textures.one integration -->";
    }
    $html = str_replace('%TITLE%', $title, $html);

    $html = str_replace('%METATITLE%', $title, $html);
    $html = str_replace('%DESCRIPTION%', $GLOBALS['SITE_DESCRIPTION'], $html);
    $keywords = $GLOBALS['SITE_TAGS'];
    if ($t1 != ""){
        $keywords = $t1['tags'] . "," . $keywords;
    }
    $html = str_replace('%KEYWORDS%', $keywords, $html);

    $author = $GLOBALS['DEFAULT_AUTHOR'];
    if ($t1 != ""){
        $author = $t1['author'];
    }
    $html = str_replace('%AUTHOR%', $author, $html);

    if ($canonical != ""){
        $html = str_replace('%URL%', $canonical, $html);
    }else{
        $html = str_replace('%URL%', $GLOBALS['SITE_URL'].$_SERVER['REQUEST_URI'], $html);
    }

    if ($slug != ""){
        $preview_img = $GLOBALS['META_URL_BASE']."{$slug}.jpg";
        $html = str_replace('%FEATURE%', $preview_img, $html);
        if ($t1 != ""){
            $textures_one = "<!-- START Textures.one integration -->\n";
            $textures_one .= "<meta name=\"tex1:name\" content=\"".$t1['name']."\" />\n";
            $textures_one .= "<meta name=\"tex1:tags\" content=\"".$t1['tags']."\" />\n";
            $textures_one .= "<meta name=\"tex1:preview-image\" content=\"$preview_img\" />\n";
            $textures_one .= "<meta name=\"tex1:type\" content=\"".$GLOBALS['TEX1_CONTENT_TYPE']."\" />\n";
            $textures_one .= "<meta name=\"tex1:method\" content=\"".$GLOBALS['TEX1_CONTENT_METHOD']."\" />\n";
            $textures_one .= "<meta name=\"tex1:license\" content=\"cc0\" />\n";
            $textures_one .= "<meta name=\"tex1:releasedate\" content=\"".$t1['date_published']."\" />\n";
            $textures_one .= "<!-- END Textures.one integration -->";
        }
    }else{
        $html = str_replace('%FEATURE%', $GLOBALS['SITE_URL']."/feature.jpg", $html);
    }

    $html = str_replace('%TEXTURESONE%', $textures_one, $html);

    echo $html;
}

function insert_email($text="##email##"){
    $email = "info@".$GLOBALS['SITE_DOMAIN'];
    echo '<a href="mailto:'.$email.'" target="_blank">';
    if ($text == "##email##"){
        echo $email;
    }else{
        echo $text;
    }
    echo "</a>";
}

function make_grid_link($sort="popular", $search="all", $category="all", $author="all"){
    $default_sort = "popular";
    $default_search = "all";
    $default_category = "all";
    $default_author = "all";

    $url = "/".$GLOBALS['CONTENT_TYPE']."/";

    $params = [];
    if ($sort != $default_sort){
        array_push($params, "o=".$sort);
    }
    if ($category != $default_category){
        array_push($params, "c=".$category);
    }
    if ($author != $default_author){
        array_push($params, "a=".$author);
    }
    if ($search != $default_search){
        array_push($params, "s=".$search);
    }

    if (empty($params)){
        return $url;
    }

    $url .= "?";
    $url .= implode("&amp;", $params);
    return $url;
}

function get_thumbnail($orig_fp, $size, $quality=55){
    $hash = md5($orig_fp.filesize($orig_fp));
    $thumb_dir = join_paths($GLOBALS['SYSTEM_ROOT'], "files/".$GLOBALS['CONTENT_TYPE_SHORT']."_images/thumbnails");
    qmkdir($thumb_dir);
    $icon_path = join_paths($thumb_dir, "{$hash}_{$size}_{$quality}.jpg");
    if (!file_exists($icon_path)){
        $bg_color = "rgb(45, 45, 45)";
        $tmp_img = new imagick($orig_fp);
        $img = new imagick();
        $img->newImage($size, $size, $bg_color);
        $tmp_img->resizeImage($size, $size, imagick::FILTER_BOX, 1, true);
        $img->compositeimage($tmp_img, Imagick::COMPOSITE_OVER, 0, 0);
        $img->setImageFormat('jpg');
        $img->setImageCompression(Imagick::COMPRESSION_JPEG);
        $img->setImageCompressionQuality($quality);
        $img->writeImage($icon_path);
    }
    return $icon_path;
}

function get_slug_thumbnail($slug, $size, $quality=55){
    $t = $GLOBALS['CONTENT_TYPE_SHORT'];
    $renders_dir = [
        "mod" => "renders",
        "tex" => "spheres",
    ];
    return get_thumbnail(join_paths($GLOBALS['SYSTEM_ROOT'], "files/".$t."_images",$renders_dir[$t], $slug.'.png'), $size, $quality);
}

function insert_ad($name){
    $fp = $_SERVER['DOCUMENT_ROOT']."/php/html/ads/".$name.".php";
    if (file_exists($fp)){
        include($fp);
    }else{
        debug_console("No ad found with name: ".$name);
    }
}

function get_ad_html($name){
    $fp = $_SERVER['DOCUMENT_ROOT']."/php/html/ads/".$name.".php";
    if (file_exists($fp)){
        return file_get_contents($fp);
    }else{
        debug_console("No ad found with name: ".$name);
        return "";
    }
}

function paypal_email_to_link($email, $description){
    return "https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business={$email}&item_name={$description}";
}


// ============================================================================
// Database functions
// ============================================================================

function db_conn_read_only(){
    $servername = $GLOBALS['DB_SERV'];
    $dbname = $GLOBALS['DB_NAME'];
    $username = $GLOBALS['DB_USER_R'];
    $password = $GLOBALS['DB_PASS_R'];
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    mysqli_set_charset($conn, 'utf8');
    return $conn;
}

function db_conn_read_write(){
    $servername = $GLOBALS['DB_SERV'];
    $dbname = $GLOBALS['DB_NAME'];
    $username = $GLOBALS['DB_USER'];
    $password = $GLOBALS['DB_PASS'];
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    mysqli_set_charset($conn, 'utf8');
    return $conn;
}

function num_items($search="all", $category="all", $reuse_conn=NULL){
    $size = 0;

    // Create connection
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $search_text = make_search_SQL(mysqli_real_escape_string($conn, $search), $category, "all");

    $sql = "SELECT name FROM ".$GLOBALS['CONTENT_TYPE']." ".$search_text;
    $rows = mysqli_query($conn, $sql)->num_rows;

    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $rows;
}

function get_from_db($sort="popular", $search="all", $category="all", $author="all", $reuse_conn=NULL, $limit=0){
    $sort_text = make_sort_SQL($sort);

    // Create connection
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $search_text = make_search_SQL(mysqli_real_escape_string($conn, $search), $category, $author);

    $sql = "SELECT * FROM ".$GLOBALS['CONTENT_TYPE']." ".$search_text." ".$sort_text;
    if ($limit > 0){
        $sql .= " LIMIT ".$limit;
    }
    $result = mysqli_query($conn, $sql);

    $array = array();
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $array[$row['name']] = $row;
        }
    }
    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $array;
}

function get_item_from_db($item, $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $row = 0; // Default incase of SQL error
    $sql = "SELECT * FROM ".$GLOBALS['CONTENT_TYPE']." WHERE slug='".$item."'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
    }

    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $row;
}

function track_search($search_term, $category="", $reuse_conn=NULL){
    if ($search_term != "all"){
        if (is_null($reuse_conn)){
            $conn = db_conn_read_write();
        }else{
            $conn = $reuse_conn;
        }
        $search_term = mysqli_real_escape_string($conn, $search_term);
        $category = mysqli_real_escape_string($conn, $category);

        $sql = "INSERT INTO searches (`category`, `search_term`) ";
        $sql .= "VALUES (\"".$category."\", \"".$search_term."\")";
        $result = mysqli_query($conn, $sql);

        if (is_null($reuse_conn)){
            $conn->close();
        }
    }
}

function get_download_count($item_id, $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $num = 0; // Default incase of SQL error
    $sql = "SELECT COUNT(DISTINCT(ip)) as dl FROM `download_counting` ";
    $sql .= "WHERE ".$GLOBALS['CONTENT_TYPE_SHORT']."_id =".$item_id;
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $num = $row['dl'];
    }

    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $num;
}

function get_similar($slug, $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $items = get_from_db("popular", "all", "all", "all", $conn);
    if (is_null($reuse_conn)){
        $conn->close();
    }

    $this_item = array();
    foreach ($items as $row){
        if ($row['slug'] == $slug){
            $this_item = $row;
            break;
        }
    }
    if (!$this_item){
        // Unpublished items will not be in 'get_from_db', so just don't show their similar items
        return NULL;
    }
    $similarities = array();
    foreach ($items as $row){
        $row_slug = $row['slug'];
        if ($row_slug != $slug){
            $cats = explode(";", $row['categories']);
            foreach ($cats as $cat){
                if (strpos((';'.$this_item['categories'].';'), (';'.$cat.';')) !== FALSE){
                    if (array_key_exists($row_slug, $similarities)){
                        $similarities[$row_slug] = $similarities[$row_slug] + 1;
                    }else{
                        $similarities[$row_slug] = 1;
                    }
                }
            }
            $tags = explode(";", $row['tags']);
            foreach ($tags as $tag){
                if (strpos((';'.$this_item['tags'].';'), (';'.$tag.';')) !== FALSE){
                    if (array_key_exists($row_slug, $similarities)){
                        $similarities[$row_slug] = $similarities[$row_slug] + 1;
                    }else{
                        $similarities[$row_slug] = 1;
                    }
                }
            }
        }
    }
    arsort($similarities);
    $similar_slugs = array_slice(array_keys($similarities), 0, 6);  // only the first 6 keys

    $similar = array();
    foreach ($similar_slugs as $s){
        foreach ($items as $i){
            if ($i['slug'] == $s){
                array_push($similar, $i);
            }
        }
    }

    return $similar;
}

function most_popular_in_each_category($reuse_conn=NULL){
    // Return array with single most popular item for each category (keys)

    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }

    $a = [];
    $items = get_from_db("popular", "all", "all", "all", $conn);
    if (array_key_exists('STANDARD_CATEGORIES', $GLOBALS)){
        $cats = array_keys($GLOBALS['STANDARD_CATEGORIES']);
    }else{
        $cats = get_all_categories($conn);
    }
    foreach ($cats as $c){
        $c = strtolower($c);
        $found = false;
        foreach ($items as $h){
            $category_arr = explode(';', strtolower($h['categories']));
            if (in_array($c, $category_arr) or $c == "all"){
                $last_of_cat = $h;  // In case no unused match is found
                if (!in_array($h['slug'], array_values($a))){
                    $a[$c] = $h['slug'];
                    $found = true;
                    break;
                }
            }
        }
        if (!$found){
            $a[$c] = $last_of_cat['slug'];
        }
    }

    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $a;
}

function get_all_cats_or_tags($mode, $cat="all", $conn=NULL){
    $db = get_from_db("popular", "all", $cat, "all", $conn, 0);
    $all_flags = [];
    foreach ($db as $item){
        $flags = explode(";",  str_replace(',', ';', $item[$mode]));
        foreach ($flags as $t){
            $t = strtolower($t);
            if (!in_array($t, $all_flags)){
                array_push($all_flags, $t);
            }
        }
    }
    sort($all_flags);
    return $all_flags;
}

function get_all_categories($conn=NULL){
    // Convenience function
    return get_all_cats_or_tags("categories", "all", $conn);
}

function get_all_tags($cat="all", $conn=NULL){
    // Convenience function
    return get_all_cats_or_tags("tags", $cat, $conn);
}

function get_gallery_renders($all=false, $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $row = 0; // Default incase of SQL error
    $sql = "SELECT * FROM gallery";
    if (!$all){
        $sql .= " WHERE favourite=1 OR TIMESTAMPDIFF(DAY, date_added, now()) < 21";
    }
    $sql .= " ORDER BY POWER(clicks+10*click_weight, 0.7)/POWER(ABS(DATEDIFF(date_added, NOW()))+1, 1.1) DESC, clicks DESC, date_added DESC";
    // $sql = "SELECT * FROM gallery WHERE favourite=1 OR TIMESTAMPDIFF(DAY, date_added, now()) < 21 ORDER BY POWER(clicks+10*click_weight, 0.7)/POWER(ABS(DATEDIFF(date_added, NOW()))+1, 1.1) DESC, clicks DESC, date_added DESC";
    $result = mysqli_query($conn, $sql);

    $array = array();
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            array_push($array, $row);
        }
    }
    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $array;
}

function get_commercial_sponsors($conn){
    $row = 0; // Default incase of SQL error
    $sql = "SELECT * FROM commercial_sponsors ORDER BY active DESC, name ASC";
    $result = mysqli_query($conn, $sql);

    $array = array();
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['active']){
                array_push($array, $row);
            }
        }
    }

    return $array;
}

function insert_commercial_sponsors($heading="Also supported by:", $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }

    $comm_sponsors = get_commercial_sponsors($conn);
    if (!empty($comm_sponsors)){
        echo "<div class='segment-a'>";
        echo "<div class='segment-inner'>";
        echo "<div class='commercial_sponsors'>";
        echo "<h2>{$heading}</h2>";
        $prev_rank = 0;
        foreach ($comm_sponsors as $s){
            $cur_rank = $s['active'];

            if ($prev_rank == 2 & $cur_rank == 1){
                echo "<br>";
            }

            echo "<a href= \"".$s['link']."\" target='_blank'>";
            echo "<img src=\"/files/site_images/commercial_sponsors/";
            echo $s['logo'];
            echo "\" alt=\"";
            echo $s['name'];
            echo "\" title=\"";
            echo $s['name'];
            echo "\"";
            if ($cur_rank == 2){
                echo " class=\"diamond\"";
            }
            echo "/>";
            echo "</a>";

            $prev_rank = $cur_rank;
        }
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    if (is_null($reuse_conn)){
        $conn->close();
    }
}

function get_sponsors($slug, $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $row = 0; // Default incase of SQL error
    $sql = "SELECT * FROM sponsors WHERE `".$GLOBALS['CONTENT_TYPE_SHORT']."`='".$slug."' ORDER BY datetime ASC";
    $result = mysqli_query($conn, $sql);

    $array = array();
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            array_push($array, $row);
        }
    }
    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $array;
}

function get_author_info($name, $reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    $row = 0; // Default incase of SQL error
    $sql = "SELECT * FROM authors WHERE `name` LIKE \"{$name}\"";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
    }

    if (is_null($reuse_conn)){
        $conn->close();
    }

    return $row;
}

function make_faq(){
    $conn = db_conn_read_only();
    $sql = "SELECT * FROM faq ORDER BY id ASC";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<hr>";
            $title = htmlspecialchars($row['title']);
            $content = htmlspecialchars($row['content']);
            $anchors = explode('+', $row['anchor']);
            foreach ($anchors as $anchor){
                echo "<div class=\"anchor-wrapper\"><a class=\"anchor\" name=\"{$anchor}\"></a></div>";
            }
            echo "<a href=\"#{$anchors[0]}\"><h2>{$title}</h2></a>";
            echo md_to_html($content);
        }
    }
}


// ============================================================================
// Item Grid
// ============================================================================

function make_category_list($sort, $reuse_conn=NULL, $current="all", $show_tags=true){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }
    echo "<div class='category-list-wrapper'>";
    echo "<ul id='category-list'>";
    if (array_key_exists('STANDARD_CATEGORIES', $GLOBALS)){
        $categories = array_keys($GLOBALS['STANDARD_CATEGORIES']);
    }else{
        $categories = get_all_categories($conn);
        array_unshift($categories, "all");
    }
    foreach ($categories as $c){
        if ($c){  // Ignore uncategorized
            $num_in_cat = num_items("all", $c, $conn);
            echo "<a href=\"".make_grid_link($sort, "all", $c, "all")."\">";
            echo "<li";
            if (array_key_exists('STANDARD_CATEGORIES', $GLOBALS)){
                echo " title=\"".$GLOBALS['STANDARD_CATEGORIES'][$c]."\"";
            }
            if ($current != "all" && $c == $current){
                echo " class='current-cat'";
            }
            echo ">";
            echo "<i class=\"material-icons\">keyboard_arrow_right</i>";
            echo nice_name($c, "category");
            echo "<div class='num-in-cat'>".$num_in_cat."</div>";
            echo "</li>";
            echo "</a>";

            if ($show_tags && $c != 'all' && $c == $current){
                $db = get_from_db("popular", "all", $c, "all", $conn, 0);
                $tags_in_cat = [];
                foreach ($db as $item){
                    $flags = explode(";",  str_replace(',', ';', $item["tags"]));
                    foreach ($flags as $t){
                        $t = strtolower($t);
                        if (in_array($t, array_keys($tags_in_cat))){
                            $tags_in_cat[$t] = $tags_in_cat[$t] + 1;
                        }else{
                            $tags_in_cat[$t] = 1;
                        }
                    }
                }
                arsort($tags_in_cat);  // Sort by number of items with tag
                $keys = array_keys($tags_in_cat);
                $keys = array_slice($keys, 0, 15);  // Remove all but top 15 tags
                sort($keys);  // Sort alphabetically

                $last_tag = end($keys);
                foreach ($keys as $t){
                    echo "<a href=\"".make_grid_link($sort, $t, $c, "all")."\">";
                    echo "<li class='tag";
                    if ($t == $last_tag){
                        echo " last-tag";
                    }
                    echo "'>";
                    echo "<i class=\"material-icons\">keyboard_arrow_right</i>";
                    echo nice_name($t);
                    echo "<div class='num-in-cat'>".$tags_in_cat[$t]."</div>";
                    echo "</li>";
                    echo "</a>";
                }
            }
        }
    }
    echo "</ul>";
    echo "</div>";
}

function make_item_grid($sort="popular", $search="all", $category="all", $author="all", $conn=NULL, $limit=0){
    $items = get_from_db($sort, $search, $category, $author, $conn, $limit);
    $html = "";
    if (!$items) {
        $html .= "<p>Sorry! There are no ".$GLOBALS['CONTENT_TYPE_NAME'];
        if ($search != 'all'){
            $html .= " that match the search \"".htmlspecialchars($search)."\"";
        }
        if ($category != 'all'){
            $html .= " in the category \"".nice_name($category, "category")."\"";
        }
        if ($author != 'all'){
            $html .= " by ".$author;
        }
        $html .= " :(</p>";
    }else{
        if ($search != "all"){
            $html .= "<h2 style='padding: 0; margin: 0'>";
            $html .= sizeof($items);
            $html .= " results";
            $html .= "</h2>";
        }
        $n = -10;
        $ad_count = 0;
        foreach ($items as $i){
            $n++;
            $html .= make_grid_item($i, $category);
            if ($n % 19 == 0 && $ad_count < 6 && function_exists("make_grid_adunit")){
                $ad_count++;
                $html .= "<div class='adsense-unit'>";
                $html .= get_ad_html("Grid {$ad_count}");
                $html .= "</div>";
            }
        }
    }
    return $html;
}


// ============================================================================
// Patreon
// ============================================================================

function pledge_rank($pledge_amount){
    $pledge_rank = 1;
    if ($pledge_amount >= 5000) {
        $pledge_rank = 6;
    }else if ($pledge_amount >= 2000) {
        $pledge_rank = 5;
    }else if ($pledge_amount >= 1000){
        $pledge_rank = 4;
    }else if ($pledge_amount >= 500){
        $pledge_rank = 3;
    }else if ($pledge_amount >= 300){
        $pledge_rank = 2;
    }
    return $pledge_rank;
}

function get_name_changes($reuse_conn=NULL){
    if (is_null($reuse_conn)){
        $conn = db_conn_read_only();
    }else{
        $conn = $reuse_conn;
    }

    $sql = "SELECT * FROM patron_name_mod";
    $result = mysqli_query($conn, $sql);
    $array = array();
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $array[$row['id']] = $row;
        }
    }
    if (is_null($reuse_conn)){
        $conn->close();
    }

    $name_replacements = [];
    $add_names = [];
    $remove_names = [];
    foreach ($array as $i){
        $n_from = $i['n_from'];
        $n_to = $i['n_to'];
        if ($n_to and $n_from){
            $name_replacements[$n_from] = $n_to;
        }else if($n_to and !$n_from){
            $add_names[$n_to] = $i['rank'];
        }else{
            array_push($remove_names, $n_from);
        }
    }

    return [$name_replacements, $add_names, $remove_names];
}

function get_patreon(){
    $patreoncache = $_SERVER['DOCUMENT_ROOT'].'/php/patreon_data/_latest.json';

    // Some users request name change
    $conn = db_conn_read_only();
    list($name_replacements, $add_names, $remove_names) = get_name_changes($conn);

    // Get dummy data if working locally
    if ($GLOBALS['WORKING_LOCALLY'] && !file_exists($patreoncache)){
        $example_names = [
            "Joni Mercado",
            "S J Bennett",
            "Adam Nordgren",
            "RENDER WORX",
            "Pierre Beranger",
            "Pablo Lopez Soriano",
            "Frank Busch",
            "Sterling Roth",
            "Jonathan Sargent",
            "hector gil",
            "Philip bazel",
            "Llynara",
            "BlenderBrit",
            "william norberg",
            "Michael Szalapski",
        ];
        $patron_list = [];
        for ($i=0; $i<350; $i++){
            $pledge_rank_weights = [1,1,1,1, 2,2,2,2,2,2,2,2,2,2,2,2, 3,3,3, 4,4, 5, 6];
            $pledge_rank = $pledge_rank_weights[array_rand($pledge_rank_weights)];
            $patron_full_name = $example_names[array_rand($example_names)];
            if (array_key_exists($patron_full_name, $name_replacements)){
                $patron_full_name = $name_replacements[$patron_full_name];
            }

            if (!in_array($patron_full_name, $remove_names)){
                array_push($patron_list, [$patron_full_name, $pledge_rank]);
            }
        }
        foreach (array_keys($add_names) as $p){
            array_splice($patron_list, rand(0, sizeof($patron_list)-1), 0, [[$p, $add_names[$p]]]);
        }


        $goals = [
            [
            "amount_cents" => 150000,
            "completed_percentage" => 83,
            "description" => "<strong>Test Goal Title<br><br></strong>Test goal description :).</em>"
            ],
        ];

        $goals = array_sort($goals, "amount_cents", SORT_ASC);

        $data = [$patron_list, 1247, $goals];

        // Write to cache
        file_put_contents($patreoncache, json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }

    // Cache to avoid overusing Patreon API
    $cachetime = 120;  // How many minutes before the cache is invalid
    $cachetime *= 60;  // convert to seconds
    if (file_exists($patreoncache)) {
        if (time() - $cachetime < filemtime($patreoncache) || $GLOBALS['WORKING_LOCALLY']){
            // echo "<!-- Patreon cache ".date('H:i', filemtime($patreoncache))." -->\n";
            $str = file_get_contents($patreoncache);
            return json_decode($str, true);
        }else{
            // Keep old cache file for statistical purposes
            rename($patreoncache, $_SERVER['DOCUMENT_ROOT'].'/php/patreon_data/'.time().'.json');
        }
    }

    $patreon_tokens = [];
    $patreon_tokens_path = $_SERVER['DOCUMENT_ROOT'].'/php/patreon_tokens.json';
    if (file_exists($patreon_tokens_path)){
        $str = file_get_contents($patreon_tokens_path);
        $patreon_tokens = json_decode($str, true);
    }
    $access_token = $patreon_tokens["access_token"];
    $refresh_token = $patreon_tokens["refresh_token"];
    $api_client = new Patreon\API($access_token);
    // Get your campaign data
    $campaign_response = $api_client->fetch_campaign();

    // If the token doesn't work, get a newer one
    if ($campaign_response['errors']) {
        // Make an OAuth client
        $client_id = $GLOBALS['CLIENT_ID'];
        $client_secret = $GLOBALS['CLIENT_SECRET'];
        $oauth_client = new Patreon\OAuth($client_id, $client_secret);
        // Get a fresher access token
        $tokens = $oauth_client->refresh_token($refresh_token, null);
        // debug_email("Patreon Tokens", json_encode($tokens, JSON_PRETTY_PRINT));
        if ($tokens['access_token']) {
            $access_token = $tokens['access_token'];
            $fp = fopen($patreon_tokens_path, 'w');
            fwrite($fp, json_encode($tokens));
            fclose($fp);
        } else {
            echo "Can't fetch new tokens. Please debug, or write in to Patreon support.\n";
            print_r($tokens);
        }
        $api_client = new Patreon\API($access_token);
        $campaign_response = $api_client->fetch_campaign();
    }

    // get page after page of pledge data
    $campaign_id = $campaign_response['data'][0]['id'];
    $cursor = null;
    $patron_list = [];
    $total_earnings_c = 0;
    while (true) {
        $pledges_response = $api_client->fetch_page_of_pledges($campaign_id, 25, $cursor);
        // get all the users in an easy-to-lookup way
        $user_data = [];
        foreach ($pledges_response['included'] as $included_data) {
            if ($included_data['type'] == 'user') {
                $user_data[$included_data['id']] = $included_data;
            }
        }
        // loop over the pledges to get e.g. their amount and user name
        foreach ($pledges_response['data'] as $pledge_data) {
            $declined = $pledge_data['attributes']['declined_since'];
            if (!$declined){
                $pledge_amount = $pledge_data['attributes']['amount_cents'];
                $total_earnings_c += $pledge_amount;
                $pledge_rank = pledge_rank($pledge_amount);

                $patron_id = $pledge_data['relationships']['patron']['data']['id'];
                $patron_full_name = $user_data[$patron_id]['attributes']['full_name'];

                if (array_key_exists($patron_full_name, $name_replacements)){
                    $patron_full_name = $name_replacements[$patron_full_name];
                }

                if (!in_array($patron_full_name, $remove_names)){
                    array_push($patron_list, [$patron_full_name, $pledge_rank]);
                }
            }
        }
        // get the link to the next page of pledges
        $next_link = $pledges_response['links']['next'];
        if (!$next_link) {
            // if there's no next page, we're done!
            break;
        }
        // otherwise, parse out the cursor param
        $next_query_params = explode("?", $next_link)[1];
        parse_str($next_query_params, $parsed_next_query_params);
        $cursor = $parsed_next_query_params['page']['cursor'];
    }
    foreach (array_keys($add_names) as $p){
        array_splice($patron_list, rand(0, sizeof($patron_list)-1), 0, [[$p, $add_names[$p]]]);
    }

    $tmp = $campaign_response['included'];
    $goals = [];
    foreach ($tmp as $x){
        if ($x['type'] == 'goal'){
            array_push($goals, $x['attributes']);
        }
    }

    $goals = array_sort($goals, "amount_cents", SORT_ASC);

    $data = [$patron_list, $total_earnings_c/100, $goals];

    // Write to cache
    file_put_contents($patreoncache, json_encode($data, JSON_PRETTY_PRINT));
    return $data;
}

function goal_title($g){
    $d = $g['description'];
    $bits = explode("</strong>", $d);
    $t = $bits[0];
    $t = str_replace("<strong>", "", $t);
    $t = str_replace("<br>", "", $t);
    return $t;
}

?>
