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
}

function oalmembers_uninstall() {
	global $wpdb;
	global $oalmembers_table_name;
	$table_name = $wpdb->prefix . $oalmembers_table_name;
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
	
	delete_option( "oalmembers_db_version");
	delete_option( "oalmembers_last_update");
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
	if( 'POST' == $_SERVER['REQUEST_METHOD'])
		$records_updated = oalmembers_reload($_FILES['upload_file']['tmp_name']);
	echo '<p>In OA Lodgemaster, create a CSV export file (field delimiter: ",", no field enclosures, line ending: "\r\n", and headers as listed after each field name below) that contains:';
	echo '<ul>';
	echo '<li>BSA Person ID "BSA_person_id"</li>';
	echo '<li>OALM ID "oalmid"</li>';
	echo '<li>Dues "max_duesyear"<li>';
	echo '<li>Firstname "firstname"</li>';
	echo '<li>Lastname "lastname"</li>';
	echo '<li>Date of Birth "Birthday"</li>';
	echo '<li>Chapter "Chapter_name"</li>';
	echo '<li>Level "level"</li>';
	echo '<li>Ordeal Date "ordeal_date"</li>';
	echo '<li>Brotherhood Date "brotherhood_date"</li>';
	echo '<li>Vigil Induction Date "vigil_induction_date"</li>';
	echo '</ul></p>';
	echo '<br />';
	echo '<p>Last Updated: ' . date('F j, Y g:i:s A', get_option( "oalmembers_last_update")) . '</p>';
	echo '<form id="oalmembers_upload" name="oalmembers_upload" method="post" action="" enctype="multipart/form-data" class="oalmembers_upload_form">';
	echo '<fieldset name="upload_file" style="margin-bottom:10px;">';
	echo '<label for="upload_file" style="margin-right:10px;">File:</label>';
	echo '<input type="file" name="upload_file" />';
	echo '</fieldset>';
	echo '<input type="submit" name="upload" values="Upload" />';
	echo '</form>';
	if(isset($records_updated))
		echo "<p>Imported $records_updated records.</p>";
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
		if(null == $row['Bsa_person_id']) continue;
		if(null == $row['Birthdate']) continue;
		if(null == $row['ordeal_date']) continue;
		$bsaid = absint($row['Bsa_person_id']);
		$oalmid = absint($row['oalmid']);
		$birthdate = date('Y-m-d', strtotime($row['Birthdate']));
		$chapter = $row['Chapter_name'];
		$firstname = $row['firstname'];
		$lastname = $row['lastname'];
		$level = $row['level'];
		$ordealdate = date('Y-m-d', strtotime($row['ordeal_date']));
		$brotherhooddate = ('' == strtotime($row['brotherhood_date'])) ? null : date('Y-m-d', strtotime($row['brotherhood_date']));
		$vigildate = ('' == $row['vigil_induction_date']) ? null : date('Y-m-d', strtotime($row['vigil_induction_date']));
		$dues = absint($row['max_duesyear']);
		$wpdb->query($wpdb->prepare("INSERT INTO $table_name (bsaid, oalmid, birthdate, firstname, lastname, chapter, level, ordealdate, brotherhooddate, vigildate, dues) VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %d)", $bsaid, $oalmid, $birthdate, $firstname, $lastname, $chapter, $level, $ordealdate, $brotherhooddate, $vigildate, $dues));
		$rows_imported++;
	}
	update_option( "oalmembers_last_update", current_time('timestamp', 0));
	return $rows_imported;

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
			$birthdate = strtotime($result['birthdate']);
			$bsaid = $result['bsaid'];
			$dues = strtotime($result['dues'] . '-12-31');
			$chapter = $result['chapter'];
			$ordeal = strtotime($result['ordealdate']);
			$brotherhood = (0 == $result['brotherhooddate']) ?  strtotime(date('Y-m-d', $ordeal) . ' +10 months') : strtotime($result['brotherhooddate']);
			$vigil = (0 == $result['vigildate']) ? strtotime(date('Y-m-d', $brotherhood) . ' +2 years') : strtotime($result['vigildate']);
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
