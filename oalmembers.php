<?php
/*
Plugin Name: OALM Member Lookup
Plugin URL: http://www.tipisa.org/
Description: Allow members to lookup their member record by BSA ID and Birthdate
Version: 2.0
Author: Matt Tucker
Author URI: mailto:matt.tucker@knights.ucf.edu
License: Free To Use within Tipisa Lodge, Central Florida Council.  Contact Matt Tucker for distribution or re-use
*/

define("OALMEMBERS_PLUGIN_PATH", plugin_dir_path( __FILE__ ));
define("OALMEMBERS_PLUGIN_URL", plugins_url( NULL, __FILE__ ));

global $oalmembers_db_version, $oalmembers_table_name;
$oalmembers_db_version = "1.0";
$oalmembers_table_name = "oalmembers";

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
	add_option( "oalmembers_netami", NULL);
	add_option( "oalmembers_lodge", NULL);
	add_option( "oalmembers_style_name", array("HelveticaLTStd-Bold.otf","53",104,1057));
	add_option( "oalmembers_style_title", array("HelveticaLTStd-Cond.otf","27",155,1013));
	add_option( "oalmembers_style_netami", array("LinotypeZapfino Three.ttf","60", 250,907));
	add_option( "oalmembers_style_expire", array("HelveticaLTStd-Roman.otf","33",749,897));
	add_option( "oalmembers_style_barcode", array("code39.ttf","72",600,740));
	add_option( "oalmembers_style_code", array("Courier10PitchBT-Roman.otf","24",650,715));
}

function oalmembers_activate() {
	if( !wp_next_scheduled( 'oalmembers_cron_hourly' ) ) {
		wp_schedule_event( time(), 'hourly', 'oalmembers_cron_hourly' );
	}
	add_action ('oalmembers_cron_hourly', array( $this, 'oalmembers_delete_cardimages') );
}

function oalmembers_deactivate() {
	wp_clear_scheduled_hook('oalmembers_cron_hourly');
}

function oalmembers_uninstall() {
	global $wpdb;
	global $oalmembers_table_name;
	$table_name = $wpdb->prefix . $oalmembers_table_name;
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
	
	delete_option( "oalmembers_db_version");
	delete_option( "oalmembers_last_update");
	delete_option( "oalmembers_netami");
	delete_option( "oalmembers_lodge");
	delete_option( "oalmembers_style_name");
	delete_option( "oalmembers_style_title");
	delete_option( "oalmembers_style_netami");
	delete_option( "oalmembers_style_expire");
	delete_option( "oalmembers_style_barcode");
	delete_option( "oalmembers_style_code");

	unlink( OALMEMBERS_PLUGIN_PATH . "/card_template.png" );

	oalmembers_delete_font("all");
	
	oalmembers_delete_cardimages();
}
register_activation_hook(__FILE__, 'oalmembers_activate');
register_deactivation_hook(__FILE__, 'oalmembers_deactivate');
register_uninstall_hook(__FILE__, 'oalmembers_uninstall');


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
		if ($lodge = $_POST['update_lodge'])
			$lodge_updated = oalmembers_update_lodge($lodge);
		if ($delete_cards = $_POST['delete_cards'])
			$cards_deleted = oalmembers_delete_cardimages();
		if ($card_template = $_FILES['upload_card']['tmp_name'])
			$card_updated = oalmembers_upload_card($card_template);

		$style_name = array($_POST['font_name'], $_POST['size_name'], $_POST['x_name'], $_POST['y_name']);
		if (($style_name == get_option(oalmembers_style_name)) ? FALSE : $style_name) {
			$style_name_updated = oalmembers_update_style('name', $style_name);
		}

		$style_title = array($_POST['font_title'], $_POST['size_title'], $_POST['x_title'], $_POST['y_title']);
		if (($style_title == get_option(oalmembers_style_title)) ? FALSE : $style_title) {
			$style_title_updated = oalmembers_update_style('title', $style_title);
		}

		$style_netami = array($_POST['font_netami'], $_POST['size_netami'], $_POST['x_netami'], $_POST['y_netami']);
		if (($style_netami == get_option(oalmembers_style_netami)) ? FALSE : $style_netami) {
			$style_netami_updated = oalmembers_update_style('netami', $style_netami);
		}

		$style_expire = array($_POST['font_expire'], $_POST['size_expire'], $_POST['x_expire'], $_POST['y_expire']);
		if (($style_expire == get_option(oalmembers_style_expire)) ? FALSE : $style_expire) {
			$style_expire_updated = oalmembers_update_style('expire', $style_expire);
		}

		$style_barcode = array($_POST['font_barcode'], $_POST['size_barcode'], $_POST['x_barcode'], $_POST['y_barcode']);
		if (($style_barcode == get_option(oalmembers_style_barcode)) ? FALSE : $style_barcode) {
			$style_barcode_updated = oalmembers_update_style('barcode', $style_barcode);
		}

		$style_code = array($_POST['font_code'], $_POST['size_code'], $_POST['x_code'], $_POST['y_code']);
		if (($style_code == get_option(oalmembers_style_code)) ? FALSE : $style_code) {
			$style_code_updated = oalmembers_update_style('code', $style_code);
		}

		if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
			$n = 0;
			while (false !== ($entry = readdir($font_dir_handle))) {
				if ($entry != "." && $entry != "..") {
					if (isset($_POST['checkbox_' . $n]))
						if ($_POST['delete_font_' . $n] == $entry) {
							oalmembers_delete_font($entry);
							$deleted_fonts[] = $entry;
						}
					$n++;
				}
			}
		}

		if ($font_file = $_FILES['upload_font']['tmp_name']) {
			$font_name = $_FILES['upload_font']['name'];
			$font_uploaded = oalmembers_upload_font($font_file, $font_name);
		}
	}
	echo '<p>In OA Lodgemaster, create a CSV export file (field delimiter: ",", no field enclosures, line ending: "\r\n", and headers as listed after each field name below) that contains:';
	echo '<ul>';
	echo '<li>BSA Person ID "BSA ID"</li>';
	echo '<li>OALM ID "OALM ID"</li>';
	echo '<li>Dues "Dues Yr."<li>';
	echo '<li>Firstname "First Name"</li>';
	echo '<li>Lastname "Last Name"</li>';
	echo '<li>Suffix "Suffix"</li>';
	echo '<li>Date of Birth "Date Of Birth"</li>';
	echo '<li>Chapter "Chapter"</li>';
	echo '<li>Level "Level"</li>';
	echo '<li>Ordeal Date "Ordeal Date"</li>';
	echo '<li>Brotherhood Date "Bro. Date"</li>';
	echo '<li>Vigil Induction Date "Vigil Date"</li>';
	echo '</ul></p>';
	echo '<br />';
	echo '<form id="oalmembers_upload" name="oalmembers_upload" method="post" action="" enctype="multipart/form-data" class="oalmembers_upload_form">';
	echo '<fieldset name="upload_file" style="margin-bottom:20px;">';
	echo '<p>Last Updated: ' . date('F j, Y g:i:s A', get_option( "oalmembers_last_update")) . '</p>';
	echo '<label for="upload_file" style="margin-right:10px;">OALM Export:</label>';
	echo '<input type="file" name="upload_file" accept=".csv" />';
	echo '</fieldset>';

	echo '<fieldset name="update_netami" style="margin-bottom:20px;">';
	echo '<p>Current Netami Lekhiket: ' . get_option( "oalmembers_netami" ) . '</p>';
	echo '<label for="update_netami" style="margin-right:10px;">Netami Lekhiket:</label>';
	echo '<input type="text" name="update_netami" />';
	echo '</fieldset>';

	echo '<fieldset name="update_lodge" style="margin-bottom:20px;">';
	echo '<p>Current Lodge: ' . get_option( "oalmembers_lodge" ) . '</p>';
	echo '<label for="update_lodge" style="margin-right:10px;">Lodge:</label>';
	echo '<input type="text" name="update_lodge" />';
	echo '</fieldset>';

	echo '<fieldset name="delete_cards" style="margin-bottom:20px;">';
	echo '<label for="delete_cards" style="margin-right:10px;">Delete Generated Cards:</label>';
	echo '<input type="checkbox" name="delete_cards" />';
	echo '</fieldset>';

	echo '<fieldset name="upload_card" style="margin-bottom:20px;">';
	echo '<p><a href="' . OALMEMBERS_PLUGIN_URL . '/card_template.png" target="_blank">Current Membership Card Template</a></p>';
	echo '<label for="upload_card" style="margin-right:10px;">Membership Card Template:</label>';
	echo '<input type="file" name="upload_card" accept="image/*" />';
	echo '</fieldset>';

	echo '<fieldset name="manage_fonts" style="margin-bottom:20px;">';
	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		echo '<p>Fonts available:</p>';
		echo '<table>';
		echo '<tr>';
		echo '<td>Delete</td>';
		echo '<td>&nbsp;</td>';
		echo '</tr>';

		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<tr>';
				echo '<td><input type="checkbox" ';
				if ($entry == get_option("oalmembers_style_name")[0] || $entry == get_option("oalmembers_style_title")[0] || $entry == get_option("oalmembers_style_netami")[0] || $entry == get_option("oalmembers_style_expire")[0] || $entry == get_option("oalmembers_style_barcode")[0] || $entry == get_option("oalmembers_style_code")[0]) {
					echo 'disabled="disabled" ';
					$disabled = TRUE;
				} else {
					$disabled = FALSE;
				}
				echo 'name="checkbox_' . $n . '" /></td>';
				echo '<td><input type="hidden" name="delete_font_' . $n . '" value="' . $entry . '" /></td>';
				echo '<td>' . $entry;
				if ($disabled)
					echo ' (in use)';
				echo '</td>';
				echo '</tr>';
				$n++;
			}
		}

		echo '</table>';
		closedir($font_dir_handle);
	}
	echo '<label for="upload_font" style="margin-right:10px;">Font file:</label>';
	echo '<input type="file" name="upload_font" />';
	echo '</fieldset>';
	echo '<fieldset name="card_format" style="margin-bottom:20px;">';

	echo '<p>Name:</p>';
	echo '<label for="font_name" style="margin-right:10px;">Font:</label>';
	echo '<select name="font_name" style="margin-right:10px;">';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<option '; 
				if ($entry == get_option("oalmembers_style_name")[0])
					echo 'selected="selected" ';
				echo 'value="' . $entry . '" />' . $entry . '</option>';
				$n++;
			}
		}
		closedir($font_dir_handle);
	}

	echo '</select>';
	echo '<label for="size_name" style="margin-right:10px;">Size:</label>';
	echo '<input type="text" name="size_name" size="3" value="' . get_option("oalmembers_style_name")[1] . '" style="text-align:right; margin-right:10px;" />';
	echo '<label for="x_name" style="margin-right:10px;">X:</label>';
	echo '<input type="text" name="x_name" size="3" value="' . get_option("oalmembers_style_name")[2] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '<label for="y_name" style="margin-right:10px;">Y:</label>';
	echo '<input type="text" name="y_name" size="3" value="' . get_option("oalmembers_style_name")[3] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';

	echo '<p>Title:</p>';
	echo '<label for="font_title" style="margin-right:10px;">Font:</label>';
	echo '<select name="font_title" style="margin-right:10px;">';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<option '; 
				if ($entry == get_option("oalmembers_style_title")[0])
					echo 'selected="selected" ';
				echo 'value="' . $entry . '" />' . $entry . '</option>';
				$n++;
			}
		}
		closedir($font_dir_handle);
	}

	echo '</select>';
	echo '<label for="size_title" style="margin-right:10px;">Size:</label>';
	echo '<input type="text" name="size_title" size="3" value="' . get_option("oalmembers_style_title")[1] . '" style="text-align:right; margin-right:10px;" />';
	echo '<label for="x_title" style="margin-right:10px;">X:</label>';
	echo '<input type="text" name="x_title" size="3" value="' . get_option("oalmembers_style_title")[2] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '<label for="y_title" style="margin-right:10px;">Y:</label>';
	echo '<input type="text" name="y_title" size="3" value="' . get_option("oalmembers_style_title")[3] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';

	echo '<p>Netami Lekhiket:</p>';
	echo '<label for="font_netami" style="margin-right:10px;">Font:</label>';
	echo '<select name="font_netami" style="margin-right:10px;">';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<option '; 
				if ($entry == get_option("oalmembers_style_netami")[0])
					echo 'selected="selected" ';
				echo 'value="' . $entry . '" />' . $entry . '</option>';
				$n++;
			}
		}
		closedir($font_dir_handle);
	}

	echo '</select>';
	echo '<label for="size_netami" style="margin-right:10px;">Size:</label>';
	echo '<input type="text" name="size_netami" size="3" value="' . get_option("oalmembers_style_netami")[1] . '" style="text-align:right; margin-right:10px;" />';
	echo '<label for="x_netami" style="margin-right:10px;">X:</label>';
	echo '<input type="text" name="x_netami" size="3" value="' . get_option("oalmembers_style_netami")[2] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '<label for="y_netami" style="margin-right:10px;">Y:</label>';
	echo '<input type="text" name="y_netami" size="3" value="' . get_option("oalmembers_style_netami")[3] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';

	echo '<p>Expiration Date:</p>';
	echo '<label for="font_expire" style="margin-right:10px;">Font:</label>';
	echo '<select name="font_expire" style="margin-right:10px;">';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<option '; 
				if ($entry == get_option("oalmembers_style_expire")[0])
					echo 'selected="selected" ';
				echo 'value="' . $entry . '" />' . $entry . '</option>';
				$n++;
			}
		}
		closedir($font_dir_handle);
	}

	echo '</select>';
	echo '<label for="size_expire" style="margin-right:10px;">Size:</label>';
	echo '<input type="text" name="size_expire" size="3" value="' . get_option("oalmembers_style_expire")[1] . '" style="text-align:right; margin-right:10px;" />';
	echo '<label for="x_expire" style="margin-right:10px;">X:</label>';
	echo '<input type="text" name="x_expire" size="3" value="' . get_option("oalmembers_style_expire")[2] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '<label for="y_expire" style="margin-right:10px;">Y:</label>';
	echo '<input type="text" name="y_expire" size="3" value="' . get_option("oalmembers_style_expire")[3] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';

	echo '<p>OALM ID Barcode:</p>';
	echo '<label for="font_barcode" style="margin-right:10px;">Font:</label>';
	echo '<select name="font_barcode" style="margin-right:10px;">';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<option '; 
				if ($entry == get_option("oalmembers_style_barcode")[0])
					echo 'selected="selected" ';
				echo 'value="' . $entry . '" />' . $entry . '</option>';
				$n++;
			}
		}
		closedir($font_dir_handle);
	}

	echo '</select>';
	echo '<label for="size_barcode" style="margin-right:10px;">Size:</label>';
	echo '<input type="text" name="size_barcode" size="3" value="' . get_option("oalmembers_style_barcode")[1] . '" style="text-align:right; margin-right:10px;" />';
	echo '<label for="x_barcode" style="margin-right:10px;">X:</label>';
	echo '<input type="text" name="x_barcode" size="3" value="' . get_option("oalmembers_style_barcode")[2] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '<label for="y_barcode" style="margin-right:10px;">Y:</label>';
	echo '<input type="text" name="y_barcode" size="3" value="' . get_option("oalmembers_style_barcode")[3] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';

	echo '<p>OALM ID Barcode Label:</p>';
	echo '<label for="font_code" style="margin-right:10px;">Font:</label>';
	echo '<select name="font_code" style="margin-right:10px;">';

	if ($font_dir_handle = opendir(OALMEMBERS_PLUGIN_PATH . "/fonts/")) {
		$n = 0;
		while (false !== ($entry = readdir($font_dir_handle))) {
			if ($entry != "." && $entry != "..") {
				echo '<option '; 
				if ($entry == get_option("oalmembers_style_code")[0])
					echo 'selected="selected" ';
				echo 'value="' . $entry . '" />' . $entry . '</option>';
				$n++;
			}
		}
		closedir($font_dir_handle);
	}

	echo '</select>';
	echo '<label for="size_code" style="margin-right:10px;">Size:</label>';
	echo '<input type="text" name="size_code" size="3" value="' . get_option("oalmembers_style_code")[1] . '" style="text-align:right; margin-right:10px;" />';
	echo '<label for="x_code" style="margin-right:10px;">X:</label>';
	echo '<input type="text" name="x_code" size="3" value="' . get_option("oalmembers_style_code")[2] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '<label for="y_code" style="margin-right:10px;">Y:</label>';
	echo '<input type="text" name="y_code" size="3" value="' . get_option("oalmembers_style_code")[3] . '" style="text-align:right;" /><span style="margin-right:10px;">px</span>';
	echo '</fieldset>';

	echo '<input type="submit" name="upload" values="Upload" />';

	$card_url = oalmembers_generate_card(TRUE, "First", "Lastname", "Jr.", "Level", "Chaptername", strtotime(date('Y') . "-12-31"), "0000000");
	echo '<p>Preview:</p>';
	echo '<img class="oalmembers_print" src="' . $card_url . '" style="width:3.53in;" />';
	echo '</form>';
	if(isset($records_updated))
		echo "<p>Imported $records_updated records.</p>";
	if(isset($netami_updated))
		echo '<p>Updated Netami Lekhiket to ' . $netami_updated . '.</p>';
	if(isset($lodge_updated))
		echo '<p>Updated Lodge to ' . $lodge_updated . '.</p>';
	if(isset($cards_deleted))
		echo '<p>Cards deleted.</p>';
	if(isset($card_updated))
		echo '<p>Membership Card Template Updated: <a href="' . $card_updated . '" target="_blank">New Membership Card Template</a> (image may not appear to have changed due to browser caching)</p>';
	if(isset($style_name_updated))
		echo '<p>Name Style Updated</p>';
	if(isset($style_title_updated))
		echo '<p>Title Style Updated</p>';
	if(isset($style_netami_updated))
		echo '<p>Netami Style Updated</p>';
	if(isset($style_expire_updated))
		echo '<p>Expire Style Updated</p>';
	if(isset($style_barcode_updated))
		echo '<p>OALM ID Barcode Style Updated</p>';
	if(isset($style_code_updated))
		echo '<p>OALM ID Barcode Label Style Updated</p>';
	if(isset($deleted_fonts))
		foreach ($deleted_fonts as $font)
			echo '<p>Deleted "' . $font . '"</p>';
	if(isset($font_uploaded))
		echo '<p>Font "' . $font_uploaded . '" uploaded</p>';
	echo '</div>';
}

function oalmembers_importcsv($file,$head=false,$delim=",",$len=1000) {
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
	$arrData = oalmembers_importcsv($file,true);
	$wpdb->query("DELETE FROM $table_name");
	$rows_imported = 0;
	foreach ($arrData as $row) {
		if(null == $row['BSA ID']) continue;
		if(null == $row['Date Of Birth']) continue;
		if(null == $row['Ordeal Date']) continue;
		$bsaid = absint($row['BSA ID']);
		$oalmid = absint($row['OALM ID']);
		$birthdate = date('Y-m-d', strtotime($row['Date Of Birth']));
		$chapter = $row['Chapter'];
		$firstname = $row['First Name'];
		$lastname = $row['Last Name'];
		$suffix = $row['Suffix'];
		$level = $row['Level'];
		$ordealdate = date('Y-m-d', strtotime($row['Ordeal Date']));
		$brotherhooddate = ('' == strtotime($row['Bro. Date'])) ? null : date('Y-m-d', strtotime($row['Bro. Date']));
		$vigildate = ('' == $row['Vigil Date']) ? null : date('Y-m-d', strtotime($row['Vigil Date']));
		$dues = absint($row['Dues Yr.']);
		$wpdb->query($wpdb->prepare("INSERT INTO $table_name (bsaid, oalmid, birthdate, firstname, lastname, suffix, chapter, level, ordealdate, brotherhooddate, vigildate, dues) VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)", $bsaid, $oalmid, $birthdate, $firstname, $lastname, $suffix, $chapter, $level, $ordealdate, $brotherhooddate, $vigildate, $dues));
		$rows_imported++;
	}
	update_option( "oalmembers_last_update", current_time('timestamp', 0));
	oalmembers_delete_cardimages();
	return $rows_imported;
}

function oalmembers_update_netami($netami) {
	update_option( "oalmembers_netami", $netami);
	oalmembers_delete_cardimages();
	return $netami;
}

function oalmembers_update_lodge($lodge) {
	update_option( "oalmembers_lodge", $lodge);
	oalmembers_delete_cardimages();
	return $lodge;
}

function oalmembers_upload_card($file) {
	$target = OALMEMBERS_PLUGIN_PATH . "/card_template.png";
	move_uploaded_file( $file, $target ); 
	$template_url = OALMEMBERS_PLUGIN_URL . '/card_template.png';

	return $template_url;
}

function oalmembers_upload_font($file, $name) {
	$target = OALMEMBERS_PLUGIN_PATH . "/fonts/" . $name;
	move_uploaded_file( $file, $target ); 

	return $name;
}

function oalmembers_generate_card($overwrite, $firstname, $lastname, $suffix, $level, $chapter, $expire, $oalmid) {
	get_option( "oalmembers_lodge" );
	$netami = get_option( "oalmembers_netami" );
	$template = OALMEMBERS_PLUGIN_PATH . "/card_template.png";
	$font_dir = OALMEMBERS_PLUGIN_PATH . "/fonts/";

	$style_name = get_option("oalmembers_style_name");
	$name_opt = array(
		"font" => $font_dir . $style_name[0],
		"size" => $style_name[1],
		"x-coord" => $style_name[2],
		"y-coord" => $style_name[3],
	);

	$style_title = get_option("oalmembers_style_title");
	$title_opt = array(
		"font" => $font_dir . $style_title[0],
		"size" => $style_title[1],
		"x-coord" => $style_title[2],
		"y-coord" => $style_title[3],
	);

	$style_netami = get_option("oalmembers_style_netami");
	$netami_opt = array(
		"font" => $font_dir . $style_netami[0],
		"size" => $style_netami[1],
		"x-coord" => $style_netami[2],
		"y-coord" => $style_netami[3],
	);

	$style_expire = get_option("oalmembers_style_expire");
	$expire_opt = array(
		"font" => $font_dir . $style_expire[0],
		"size" => $style_expire[1],
		"x-coord" => $style_expire[2],
		"y-coord" => $style_expire[3],
	);

	$style_barcode = get_option("oalmembers_style_barcode");
	$barcode_opt = array(
		"font" => $font_dir . $style_barcode[0],
		"size" => $style_barcode[1],
		"x-coord" => $style_barcode[2],
		"y-coord" => $style_barcode[3],
	);

	$style_code = get_option("oalmembers_style_code");
	$code_opt = array(
		"font" => $font_dir . $style_code[0],
		"size" => $style_code[1],
		"x-coord" => $style_code[2],
		"y-coord" => $style_code[3],
	);

	if($level == "Ordeal") {
        	$level_article = "an";
	}
	else {
        	$level_article = "a";
	}

	if($level == "Vigil")
        	$level_suffix = " Honor";

	$hashed_id = sha1($oalmid . date('Y-m-d'));
	$path = OALMEMBERS_PLUGIN_PATH . "/generated/";
	$filename = $hashed_id . ".png";
	$full_path = $path . $filename;
	$card_url = OALMEMBERS_PLUGIN_URL . "/generated/" . $filename;

	if(file_exists($template) && ($overwrite == TRUE || (!(file_exists($full_path) && is_file($full_path)) && $overwrite == FALSE))) {
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
		$image->annotateImage($draw_title,$title_opt['x-coord'],$height-$title_opt['y-coord'],0,"is " . $level_article . " " . $level . $level_suffix . " member in\n" . $chapter . " Chapter, " . get_option( "oalmembers_lodge" ) . " Lodge");

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

		$draw_barcode = new ImagickDraw();
		$draw_barcode->setFillColor('#000000');
		$draw_barcode->setFont($barcode_opt['font']);
		$draw_barcode->setFontSize($barcode_opt['size']);
		$image->annotateImage($draw_barcode,$barcode_opt['x-coord'],$height-$barcode_opt['y-coord'],0, "*" . $oalmid . "*");

		$draw_code = new ImagickDraw();
		$draw_code->setFillColor('#000000');
		$draw_code->setFont($code_opt['font']);
		$draw_code->setFontSize($code_opt['size']);
		$image->annotateImage($draw_code,$code_opt['x-coord'],$height-$code_opt['y-coord'],0, $oalmid);

		if(!(file_exists($path) && is_dir($path)))
        		mkdir($path, 0755);
      	 	$image->writeimage($full_path);
	}

	return $card_url;
}

function oalmembers_update_style($block,$style) {
	update_option('oalmembers_style_' . $block, $style);

	return 0;
}

function oalmembers_delete_font($font_name) {
	if($font_name == "all") {
		$files = glob(OALMEMBERS_PLUGIN_PATH . "fonts/*"); // get all file names
		foreach($files as $file){ // iterate files
			if(is_file($file))
				unlink($file); // delete file
		}
		rmdir(OALMEMBERS_PLUGIN_PATH . "fonts");
	}
	else {
		if ($font_name != get_option("oalmembers_style_name")[0] && $font_name != get_option("oalmembers_style_title")[0] && $font_name != get_option("oalmembers_style_netami")[0] && $font_name != get_option("oalmembers_style_expire")[0]) {
			unlink( OALMEMBERS_PLUGIN_PATH . "fonts/" . $font_name );
		}
	}

	return 0;
}

function oalmembers_delete_cardimages() {
	$files = glob(OALMEMBERS_PLUGIN_PATH . "/generated/*.png"); // get all file names
	foreach($files as $file){ // iterate files
		if(is_file($file))
			unlink($file); // delete file
	}

	return 0;
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
			$html .= "ss.href ='" . OALMEMBERS_PLUGIN_URL . "/style.css';";
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

			$card_url = oalmembers_generate_card(FALSE, $firstname, $lastname, $suffix, $level, $chapter, $dues, $oalmid);
			$html .= '<img class="oalmembers_print" src="' . $card_url . '" style="width:3.53in;" />';
			$html .= "<p><a href='javascript:window.print()'>Print Membership Card</a></p>";
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
	$html .= '<input type="text" id="bsaid" value="" name="bsaid"/>';
	$html .= '</fieldset>';
	$html .= '<fieldset name="birthdate" style="margin-bottom:10px;">';
	$html .= '<label for="birthdate" style="margin-right:10px;">Birthdate:</label>';
	$html .= '<input type="text" id="birthdate" value="" name="birthdate" placeholder="MM/dd/yyyy"/>';
	$html .= '</fieldset>';
	$html .= '<fieldset class="submit">';
	$html .= '<input type="submit" value="Search" id="submit"/>';
	$html .= '</fieldset>';
	$html .= '<input type="hidden" name="action" value="oalmembers search"/>';
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
