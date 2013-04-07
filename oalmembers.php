<?php
/*
Plugin Name: OALM Member Lookup
Plugin URL: http://www.tipisa.org/
Description: Allow members to lookup their member record by BSA ID and Birthdate
Version: 1.0
Author: Matt Tucker
Author URI: mailto:matt.tucker@knights.ucf.edu
License: Free To Use within Tipisa Lodge, Central Florida Council.  Contact Matt Tucker for distribution or re-use
*/

define("OALMEMBERS_PLUGIN_PATH", plugin_dir_path( __FILE__ ));
define("OALMEMBERS_PLUGIN_URL", plugins_url( NULL, __FILE__ ));

global $oalmembers_db_version, $oalmembers_table_name, $oalmembers_lodge;
$oalmembers_db_version = "1.0";
$oalmembers_table_name = "oalmembers";
$oalmembers_lodge = "Tipisa";

function oalmembers_install() {
	global $wpdb;
	global $oalmembers_table_name;
	$table_name = $wpdb->prefix . $oalmembers_table_name;
	$table_installed = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
	$table_is_current = (get_option( "oalmembers_db_version" )==$oalmembers_db_version);
	if( ( ! $table_installed ) || ( ! $table_is_current ) ) {
		$sql = "CREATE TABLE " . $table_name . " (
					bsaid int NOT NULL ,
					oalmid int NOT NULL ,
					birthdate date NOT NULL ,
					firstname varchar(100) NOT NULL ,
					lastname varchar(100) NOT NULL ,
					suffix varchar(100) ,
					chapter varchar(100) ,
					level varchar (100) NOT NULL,
					ordealdate date NOT NULL ,
					brotherhooddate date ,
					vigildate date ,
					dues YEAR ,
					UNIQUE KEY  (bsaid)
					);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	if( ! $table_installed )
		add_option( "oalmembers_db_version", $oalmembers_db_version);
	if( ! $table_is_current )
		update_option( "oalmembers_db_version", $oalmembers_db_version);
	add_option( "oalmembers_last_update", 0);
	add_option( "oalmembers_netami", 0);
}

function oalmembers_uninstall() {
	global $wpdb;
	global $oalmembers_table_name;
	$table_name = $wpdb->prefix . $oalmembers_table_name;
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
	
	delete_option( "oalmembers_db_version");
	delete_option( "oalmembers_last_update");
	delete_option( "oalmembers_netami");

	unlink( OALMEMBERS_PLUGIN_PATH . "/card_template.png" );
}

function oalmembers_menu() {
	add_submenu_page('tools.php', 'OALM Member Options', 'OALM Member Lookup', 'manage_options', 'oalmembers_options', 'oalmembers_options');
}

function oalmembers_options() {
	if(!current_user_can('manage_options')) {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	echo '<div class="wrap">';
	$rows_imported = 0;
	if( 'POST' == $_SERVER['REQUEST_METHOD']) {
		if ($oalm_roster = $_FILES['upload_file']['tmp_name'])
			$records_updated = oalmembers_reload($oalm_roster);
		if ($netami = $_POST['update_netami'])
			$netami_updated = oalmembers_update_netami($netami);
		if ($card_template = $_FILES['upload_card']['tmp_name'])
			$card_updated = oalmembers_upload_card($card_template);
	}
	echo '<p>In OA Lodgemaster, create a CSV export file (field delimiter: ",", no field enclosures, line ending: "\r\n", and headers as listed after each field name below) that contains:';
	echo '<ul>';
	echo '<li>BSA Person ID "bsa_id"</li>';
	echo '<li>OALM ID "oalm_id"</li>';
	echo '<li>Dues "max_dues"<li>';
	echo '<li>Firstname "firstname"</li>';
	echo '<li>Lastname "lastname"</li>';
	echo '<li>Suffix "suffix"</li>';
	echo '<li>Date of Birth "dob"</li>';
	echo '<li>Chapter "chapter"</li>';
	echo '<li>Level "level"</li>';
	echo '<li>Ordeal Date "ordeal_date"</li>';
	echo '<li>Brotherhood Date "brotherhood_date"</li>';
	echo '<li>Vigil Induction Date "vigil_date"</li>';
	echo '</ul></p>';
	echo '<br />';
	echo '<form id="oalmembers_upload" name="oalmembers_upload" method="post" action="" enctype="multipart/form-data" class="oalmembers_upload_form">';
	echo '<fieldset name="upload_file" style="margin-bottom:10px;">';
	echo '<p>Last Updated: ' . date('F j, Y g:i:s A', get_option( "oalmembers_last_update")) . '</p>';
	echo '<label for="upload_file" style="margin-right:10px;">OALM Export:</label>';
	echo '<input type="file" name="upload_file" accept=".csv" />';
	echo '</fieldset>';
	echo '<fieldset name="update_netami" style="margin-bottom:10px;">';
	echo '<p>Current Netami Lekhiket: ' . get_option( "oalmembers_netami" ) . '</p>';
	echo '<label for="update_netami" style="margin-right:10px;">Netami Lekhiket:</label>';
	echo '<input type="text" name="update_netami" />';
	echo '</fieldset>';
	echo '<fieldset name="upload_card" style="margin-bottom:10px;">';
	echo '<p><a href="' . OALMEMBERS_PLUGIN_URL . '/card_template.png" target="_blank">Current Membership Card Template</a></p>';
	echo '<label for="upload_card" style="margin-right:10px;">Membership Card Template:</label>';
	echo '<input type="file" name="upload_card" accept="image/*" />';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		echo '<p>Fonts available:</p>';
		echo '<ul>';

		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<li>' . $entry . '</li>';
			}
		}

		closedir($font_dir_handle);
	}

	echo '</fieldset>';
	echo '<input type="submit" name="upload" values="Upload" />';
	echo '</form>';
	if(isset($records_updated))
		echo "<p>Imported $records_updated records.</p>";
	if(isset($netami_updated))
		echo '<p>Updated Netami Lekhiket to ' . $netami_updated . '.</p>';
	if(isset($card_updated))
		echo '<p>Membership Card Template Updated: <a href="' . $card_updated . '" target="_blank">New Membership Card Template</a> (image may not appear to have changed due to browser caching)</p>';
	echo '</div>';
}

function importcsv($file,$head=false,$delim=",",$len=1000) {
    $return = false;
    $handle = fopen($file, "r");
    if ($head) {
        $header = fgetcsv($handle, $len, $delim);
    }
    while (($data = fgetcsv($handle, $len, $delim)) !== FALSE) {
        if ($head AND isset($header)) {
            foreach ($header as $key=>$heading) {
                $row[$heading]=(isset($data[$key])) ? $data[$key] : '';
            }
            $return[]=$row;
        } else {
            $return[]=$data;
        }
    }
    fclose($handle);
    return $return;
}

function oalmembers_reload($file) {
	global $wpdb;
	global $oalmembers_table_name;
	$table_name = $wpdb->prefix . $oalmembers_table_name;
	$arrData = importcsv($file,true);
	$wpdb->query("DELETE FROM $table_name");
	$rows_imported = 0;
	foreach ($arrData as $row) {
		if(null == $row['bsa_id']) continue;
		if(null == $row['dob']) continue;
		if(null == $row['ordeal_date']) continue;
		$bsaid = absint($row['bsa_id']);
		$oalmid = absint($row['oalm_id']);
		$birthdate = date('Y-m-d', strtotime($row['dob']));
		$chapter = $row['chapter'];
		$firstname = $row['firstname'];
		$lastname = $row['lastname'];
		$suffix = $row['suffix'];
		$level = $row['level'];
		$ordealdate = date('Y-m-d', strtotime($row['ordeal_date']));
		$brotherhooddate = ('' == strtotime($row['brotherhood_date'])) ? null : date('Y-m-d', strtotime($row['brotherhood_date']));
		$vigildate = ('' == $row['vigil_date']) ? null : date('Y-m-d', strtotime($row['vigil_date']));
		$dues = absint($row['max_dues']);
		$wpdb->query($wpdb->prepare("INSERT INTO $table_name (bsaid, oalmid, birthdate, firstname, lastname, suffix, chapter, level, ordealdate, brotherhooddate, vigildate, dues) VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)", $bsaid, $oalmid, $birthdate, $firstname, $lastname, $suffix, $chapter, $level, $ordealdate, $brotherhooddate, $vigildate, $dues));
		$rows_imported++;
	}
	update_option( "oalmembers_last_update", current_time('timestamp', 0));
	return $rows_imported;

}

function oalmembers_update_netami($netami) {
	update_option( "oalmembers_netami", $netami);
	return $netami;
}

function oalmembers_upload_card($file) {
	$target = OALMEMBERS_PLUGIN_PATH . "/card_template.png";
	move_uploaded_file( $file, $target ); 
	$template_url = OALMEMBERS_PLUGIN_URL . '/card_template.png';

	return $template_url;
}

function oalmembers_generate_card($firstname, $lastname, $suffix, $level, $chapter, $expire, $oalmid) {
	global $oalmembers_lodge;
	$netami = get_option( "oalmembers_netami" );
	$template = OALMEMBERS_PLUGIN_PATH . "/card_template.png";
	$font_dir = OALMEMBERS_PLUGIN_PATH . "/fonts/";

	$name_opt = array(
		"font" => $font_dir . 'HelveticaLTStd-Bold.otf',
		"size" => '53',
		"x-coord" => 104,
		"y-coord" => 1057,
	);
	$title_opt = array(
		"font" => $font_dir . 'HelveticaLTStd-Cond.otf',
		"size" => 27,
		"x-coord" => 155,
		"y-coord" => 1013,
	);
	$netami_opt = array(
		"font" => $font_dir . 'LinotypeZapfino Three.ttf',
		"size" => 60,
		"x-coord" => 290,
		"y-coord" => 907,
	);
	$expire_opt = array(
		"font" => $font_dir . 'HelveticaLTStd-Roman.otf',
		"size" => 33,
		"x-coord" => 749,
		"y-coord" => 897,
	);

	if($level == "Ordeal") {
        	$level_article = "an";
	}
	else {
        	$level_article = "a";
	}

	if($level == "Vigil")
        	$level_suffix = " Honor";

	$hashed_id = sha1($oalmid);
	$path = OALMEMBERS_PLUGIN_PATH . "/generated/";
	$filename = $hashed_id . ".png";
	$full_path = $path . $filename;
	$card_url = OALMEMBERS_PLUGIN_URL . "/generated/" . $filename;

	if(!(file_exists($full_path) && is_file($full_path))) {
		$image = new Imagick($template);
		$height = $image->getimageheight();

		$draw_name = new ImagickDraw();
		$draw_name->setFillColor('#000000');
		$draw_name->setFont($name_opt['font']);
		$draw_name->setFontSize($name_opt['size']);
		$image->annotateImage($draw_name,$name_opt['x-coord'],$height-$name_opt['y-coord'],0,$firstname . " " . $lastname . " " . $suffix);

		$draw_title = new ImagickDraw();
		$draw_title->setFillColor('#000000');
		$draw_title->setFont($title_opt['font']);
		$draw_title->setFontSize($title_opt['size']);
		$image->annotateImage($draw_title,$title_opt['x-coord'],$height-$title_opt['y-coord'],0,"is " . $level_article . " " . $level . $level_suffix . " member in\n" . $chapter . " Chapter, " . $oalmembers_lodge . " Lodge");

		$draw_netami = new ImagickDraw();
		$draw_netami->setFillColor('#000000');
		$draw_netami->setFont($netami_opt['font']);
		$draw_netami->setFontSize($netami_opt['size']);
		$image->annotateImage($draw_netami,$netami_opt['x-coord'],$height-$netami_opt['y-coord'],0,$netami);

		$draw_expire = new ImagickDraw();
		$draw_expire->setFillColor('#000000');
		$draw_expire->setFont($expire_opt['font']);
		$draw_expire->setFontSize($expire_opt['size']);
		$image->annotateImage($draw_expire,$expire_opt['x-coord'],$height-$expire_opt['y-coord'],0,date('n/j/y', $expire));

		if(!(file_exists($path) && is_dir($path)))
        		mkdir($path, 0755);
      	 	$image->writeimage($full_path);
	}

	return $card_url;
}

function oalmembers_lookup_record() {
	global $oalmembers_table_name;
	global $wpdb;
	$html = "";
	$html = "<div><p>Records last updated on " . date('F j, Y', get_option( "oalmembers_last_update" )) . "</p></div>";
	if(isset ($_POST['bsaid'])&& !empty($_POST['bsaid'])) $bsaid = absint($_POST['bsaid']);
	if(isset ($_POST['birthdate']) && !empty($_POST['birthdate'])) $birthdate = date('Y-m-d', strtotime($_POST['birthdate']));
	if( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && $_POST['action'] == "oalmembers search" && isset($bsaid) && isset($birthdate)) {
		$table_name = $wpdb->prefix . $oalmembers_table_name;
		$result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE bsaid = %d AND birthdate = %s", $bsaid, $birthdate), ARRAY_A, 0);
		if(null != $result) {
			$firstname = $result['firstname'];
			$lastname = $result['lastname'];
			$suffix = $result['suffix'];
			$birthdate = strtotime($result['birthdate']);
			$bsaid = $result['bsaid'];
			$oalmid = $result['oalmid'];
			$dues = strtotime($result['dues'] . '-12-31');
			$chapter = $result['chapter'];
			$level = $result['level'];
			$ordeal = strtotime($result['ordealdate']);
			$brotherhood = (0 == $result['brotherhooddate']) ?  strtotime(date('Y-m-d', $ordeal) . ' +10 months') : strtotime($result['brotherhooddate']);
			$vigil = (0 == $result['vigildate']) ? strtotime(date('Y-m-d', $brotherhood) . ' +2 years') : strtotime($result['vigildate']);
			$html .= "<script type='text/javascript'>";
			$html .= "var ss = document.createElement('link');";
			$html .= "ss.type = 'text/css';";
			$html .= "ss.rel = 'stylesheet';";
			$html .= "ss.media = 'print';";
			$html .= "ss.href = 'http://99.189.136.160/wordpress/wp-content/plugins/oalmembers/style.css';";
			$html .= "document.getElementsByTagName('head')[0].appendChild(ss);";
			$html .= "</script>";
			$html .= "<div>";
			$html .= "<table>";
			$html .= "<tr>";
			$html .= "<td>Firstname</td>";
			$html .= "<td>$firstname</td>";
			$html .= "</tr>";
			$html .= "<tr>";
			$html .= "<td>Lastname</td>";
			$html .= "<td>$lastname</td>";
			$html .= "</tr>";
			$html .= "<tr>";
			$html .= "<td>Birthdate</td>";
			$html .= "<td>" . date('F j', $birthdate) . "</td>";
			$html .= "</tr>"; 
			$html .= "<tr>";
			$html .= "<td>BSA ID</td>";
			$html .= "<td>$bsaid</td>";
			$html .= "</tr>";
			$html .= "<tr>";
			if(null != $result['dues']) {
				$html .= "<td>Dues Expire</td>";
				$html .= "<td>" . date('F j, Y', $dues);
			} else {
				$html .= "<td>Dues</td>";
				$html .= "<td>No Record</td>";
			}
			$html .= "</tr>";
			$html .= "<tr>";
			$html .= "<td>Chapter</td>";
			$html .= "<td>$chapter</td>";
			$html .= "<tr>";
			$html .= "<td>Ordeal</td>";
			$html .= "<td>" . date('F j, Y', $ordeal) . "</td>";
			$html .= "</tr>";
			$html .= "<tr>";
			$html .= "<td>Brotherhood</td>";
			if (0 != $result['brotherhooddate'])
				$html .= "<td>" . date('F j, Y', $brotherhood) . "</td>";
			else
				$html .= "<td>Eligible on " . date('F j, Y', $brotherhood) . "</td>";
			$html .= "</tr>";
			$html .= "<tr>";
			$html .= "<td>Vigil</td>";
			if (0 != $result['vigildate'])
				$html .= "<td>" . date('F j, Y', $vigil) . "</td>";
			else
				$html .= "<td>Eligible on " . date('F j, Y', $vigil) . "</td>";
			$html .= "</tr>";
			$html .= "</table>";

			$card_url = oalmembers_generate_card($firstname, $lastname, $suffix, $level, $chapter, $dues, $oalmid);
			$html .= '<img class="oalmembers_print" src="' . $card_url . '" style="width:3.53in;" />';
			$html .= "<p><a href='javascript:window.print()'>Print Memebership Card</a></p>";
			$html .= "</div>";
		} else {
			$html .= "<div>";
			$html .= "<p>No record found with BSA ID " . $bsaid . " and birthdate " . $birthdate . "</p>";
			$html .= "</div>";
		}
	}
	$html .= "<div>";
	$html .= '<form id="oalmembers" name="oalmembers" method="post" action="" class="oalmembers-form">';
	$html .= '<fieldset name="bsaid" style="margin-bottom:10px;">';
	$html .= '<label for="bsaid" style="margin-right:10px;">BSA ID:</label>';
	$html .= '<input type="text" id="bsaid" value="" name="bsaid" />';
	$html .= '</fieldset>';
	$html .= '<fieldset name="birthdate" style="margin-bottom:10px;">';
	$html .= '<label for="birthdate" style="margin-right:10px;">Birthdate:</label>';
	$html .= '<input type="text" id="birthdate" value="" name="birthdate" />';
	$html .= '</fieldset>';
	$html .= '<fieldset class="submit">';
	$html .= '<input type="submit" value="Search" id="submit" />';
	$html .= '</fieldset>';
	$html .= '<input type="hidden" name="action" value="oalmembers search" />';
	//wp_nonce_field('new-post');
	$html .= '</form>';
	$html .= "</div>";
	return $html;
}

register_activation_hook(__FILE__,'oalmembers_install');
register_deactivation_hook(__FILE__, 'oalmembers_uninstall');
add_action('admin_menu', 'oalmembers_menu');

add_shortcode('oalmembers', 'oalmembers_lookup_record');
?>
