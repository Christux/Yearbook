<?php
/*
Plugin Name: WP Yearbook
Plugin URI: 
Description: A Wordpress plugin that generate a yearbook.
Author: Christophe RUBECK
Version: 0.1
Author URI: 
*/

/*	Copyright 2010 Christophe RUBECK  (email : christux@wanadoo.fr)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


add_filter('the_content','yearbook_insert');
add_action('admin_menu', 'yearbook_menu');
add_action('wp_head', 'yearbook_style');

// Define the tables used in YEARBOOK
define('WP_YEARBOOK_TABLE', $table_prefix . 'yearbook');
define('WP_YEARBOOK_CONFIG_TABLE', $table_prefix . 'yearbook_config');
define('WP_YEARBOOK_SUPERUSER_TABLE', $table_prefix . 'yearbook_superuser');

function yearbook_insert($content)
{
  if (preg_match('{YEARBOOK}',$content))
    {
      $cal_output = yearbook();
      $content = str_replace('{YEARBOOK}',$cal_output,$content);
    }
  return $content;
}

function yearbook_menu()
{
	  add_menu_page('Yearbook', 'Yearbook', 10, 'Yearbook', 'yearbook_config');
}

function yearbook_style()
{
	global $wpdb ;
	
	$query = "SELECT config_value FROM " . WP_YEARBOOK_CONFIG_TABLE . "  WHERE config_item='style' LIMIT 1" ;
	$result = $wpdb->get_results($query) ;
	$style = $result[0]->config_value ;
	
	// Define yearbook style
	?>
	<!-- YEARBOOK style -->
	<style type="text/css">
	<!--
	<?php echo $style ; ?>
	//-->
	</style>
	<!-- /YEARBOOK style -->
	<?php
}
  
function yearbook_config()
{
	global $wpdb ;
	$site_url = $_SERVER["PHP_SELF"] ;
	
	?><div style="background-color: #E5E9AD; padding-left: 10px; padding-right: 100px"><?php
	
	// Create the tables
	check_yearbook() ;
	
	//echo $file_path . "<br/>" ;
	
	// Update data base
	if(isset($_POST['upload']))
	{
		echo "<b>Update de la base de données</b><br/>" ;
		/*
		echo $_FILES['upfile']['tmp_name'] . "<br/>";
		echo $_FILES['upfile']['name'] . "<br/>" ;
		*/
		
		if(isset($_FILES['upfile']['name']))
		{
			echo "Fichier uploadé dans le répertoire temporaire<br/>" ;

			$get_ext = pathinfo($_FILES['upfile']['name']);
	    	if($get_ext['extension'] == "csv" )
	    	{
         		 	// Upgrade of the data base
					echo "Update en cours...<br/>" ;
					$query = "TRUNCATE TABLE " . WP_YEARBOOK_TABLE ;
					$wpdb->get_results($query) ;
					echo "Effacement des données existantes " . $wpdb->print_error() . "<br/>" ;
					
					$query = $wpdb->prepare("LOAD DATA LOCAL INFILE %s INTO TABLE " . WP_YEARBOOK_TABLE . " FIELDS TERMINATED BY ';' ENCLOSED BY '\"' ESCAPED BY '\\\' LINES TERMINATED BY '\\n'",$_FILES['upfile']['tmp_name']) ;
					$wpdb->get_results($query) ;
					echo "Importation des nouvelles données dans la base " . $wpdb->print_error() . "<br/>" ;		
	    	}
	    	else echo "Erreur, ce n'est pas un fichier *.csv<br/>" ;
			
			
			// Del the file
			echo "Suppression du fichier<br/>" ;
			@unlink($_FILES['upfile']['name']);
		}
		else
		{
			echo "Erreur lors de l'upload du fichier" ;
			if ($_FILES['upfile']['name'] == '') echo ", veuillez indiquer un fichier" ;
			echo "<br/>" ;
		}
	}
	
	// Add super users
	if (isset($_POST['add_login']))
	{
		echo "<b>Ajout d'un utilisateur privilégié</b><br/>" ;
		if ($_POST['add_login'] != '')
		{
			$query = $wpdb->prepare("INSERT INTO " . WP_YEARBOOK_SUPERUSER_TABLE . "(id,login) VALUES('', %s )",$_POST['add_login']) ;
			$wpdb->get_results($query) ;
			echo "Ajout de l'utilisateur " . $_POST['add_login'] . " " . $wpdb->print_error() . "<br/>" ;
		}
		else echo "Erreur, veuillez entrer un nom d'utilisateur valide<br/>" ;
	}
	
	// Del super users
	if(isset($_POST['del_superuser']))
	{
		echo "<b>Suppression d'utilisateur(s) privilégié(s)</b><br/>" ;
		$count=1;
		$del_count=0;
		while(isset($_POST['del_login'.$count]))
		{
			if(isset($_POST['del_login_id'.$count]))
			{
				$query = $wpdb->prepare("SELECT login FROM " . WP_YEARBOOK_SUPERUSER_TABLE . "  WHERE ID='%d' LIMIT 1",$_POST['del_login_id'.$count]) ;
				$result = $wpdb->get_results($query) ;
				$user_deleted = $result[0]->login ;
			
				// Delete user
				$query = $wpdb->prepare("DELETE FROM " . WP_YEARBOOK_SUPERUSER_TABLE . " WHERE ID=%d",$_POST['del_login_id'.$count]) ;
				$wpdb->get_results($query) ;
				echo "Suppression de l'utilisateur " . $user_deleted . " " . $wpdb->print_error() . "<br/>" ;
				$del_count++;
			}
			$count++ ;
		}
		if($del_count == 0) echo "Erreur, aucun utilisateur sélectionné<br/>" ;
	}
	
	// Update intro
	if (isset($_POST['update_intro']))
	{
		if (isset($_POST['intro']))
		{
			echo "<b>Mise à jour de l'introduction</b><br/>" ;
			$intro = stripslashes($_POST['intro']);
			$query =  $wpdb->prepare("UPDATE ".WP_YEARBOOK_CONFIG_TABLE." SET config_value = '%s' WHERE config_item='intro' LIMIT 1", utf8_encode($intro)) ;
			$wpdb->get_results($query) ;
			echo "Introduction mise à jour " . $wpdb->print_error() . "<br/>" ;
		}
	}		

	// Update style
	if (isset($_POST['update_style']))
	{
		if (isset($_POST['style']))
		{
			echo "<b>Mise à jour du style de l'annuaire</b><br/>" ;
			$style = stripslashes($_POST['style']);
			$query =  $wpdb->prepare("UPDATE ".WP_YEARBOOK_CONFIG_TABLE." SET config_value = '%s' WHERE config_item='style' LIMIT 1", $style) ;
			$wpdb->get_results($query) ;
			echo "Style mis à jour " . $wpdb->print_error() . "<br/>" ;
		}
	}		
	
	// Reset style
	if (isset($_POST['reset_style']))
	{
		echo "<b>Retour au style par défaut de l'annuaire</b><br/>" ;
		configure_default_style() ;
		echo "Style mis à jour<br/>" ;
	}
	
	?>
	</div>
	<h2>Configuration de l'Annuaire des Anciens</h2>
	<br/>
	
	<h3>Affichage</h3>
	<p>Pour afficher l'annuaire sur une page, entrer le code {YEARBOOK}.</p>
	<br/>
	
	<h3>Introduction</h3>
	<p>Tapez ici le texte d'introduction à l'annuaire.</p>
	<?php
	// Read the introduction
		$query = "SELECT config_value FROM " . WP_YEARBOOK_CONFIG_TABLE . "  WHERE config_item='intro' LIMIT 1" ;
		$result = $wpdb->get_results($query) ;
		$intro = utf8_decode($result[0]->config_value) ;
	?>
	<form  method="post" enctype="multipart/form-data" action="<?php echo  $site_url . "?page=Yearbook"; ?>">
		<textarea name="intro" cols="70" rows="6"><?php echo $intro; ?></textarea>
		<br/><input  type="submit" value="Mettre l'introduction à jour" name="update_intro">
	</form>
	<br/>
	
	<h3>Mise à jour des données</h3>
	<p>La structure de la base de données doit être au format csv (champs séparés par des ';', textes délimités par des " et les fins de lignes par des '\n') et les éléments rigoureusement agencés de la manière suivante : ID, Année de soutenance, Nom, Prénom, Sujet de thèse, Encadrants, Situation actuelle, Email.
	<br/><br/>
	Veuillez indiquer le fichier *.csv qui contient la base de données de l'annuaire.</p>

	<form  method="post" enctype="multipart/form-data" action="<?php echo  $site_url . "?page=Yearbook"; ?>">
  	<table>
  	<tr><td><input type="hidden" name="MAX_FILE_SIZE" value="1073741824" />
  	<input type="file" name="upfile" element size="30"></td><td></td></tr>
  	<tr><td></td><td><input  type="submit" value="Envoyer la mise à jour" name="upload"></td></tr>
  	</table>
  	</form>
	
	<br/>
	<h3>Utilisateurs autorisés à voir les adresses emails des Anciens</h3>
	
	<p>Pour des raisons de confidentialité, les adresses email des Anciens de sont pas accessibles.<br/>
	Il est cependant possible d'autoriser certains utilisateurs (membres du bureau d'OPLAT par exemple) à les voir.<br/>
	<br/>Entrer ici la liste des utilisateurs privilégiés.</p>
	
	<form action="<?php echo  $site_url . "?page=Yearbook"; ?>" method="post">
		<input type="text" name="add_login" size="20" maxlength="25"/>
		<input type="submit" value="Ajouter l'utilisateur" />
	</form>
	<br/>
	
	<?php
	
	// Read the superuser database
	$query = "SELECT * FROM " . WP_YEARBOOK_SUPERUSER_TABLE . "  ORDER BY id" ;
	$result = $wpdb->get_results($query) ;
	?>
	<form action="<?php echo  $site_url . "?page=Yearbook"; ?>" method="post">
		<table style="width: 200px; padding: 5px;">
		<tr><th>Login</th><th style="width:10px"></th></tr>
		<?php
		$count=1 ;
		foreach($result as $element)
		{
			echo "<tr><td>" . $element->login . "</td>" ;
			
			echo '<td style="text-align: center">' ;
			echo '<input type="hidden" name="del_login' . $count . '" value="' . $element->id . '" />' ;
			echo '<input type="checkbox" name="del_login_id' . $count . '" value="' . $element->id . '" />' ;
			echo '</td></tr>' ;
			$count++;
		}
		?>
		</table>
		<br/>
		<input type="hidden" name="del_superuser" value="true" />
		<input type="submit" value="Suppression des utilisateurs" />
	</form>
	
	<br/><br/>
	
	<h3>Style de l'annuaire</h3>
	<p>Modifiez ici la feuille de style de l'annuaire.</p>
	<?php
	// Read the style
		$query = "SELECT config_value FROM " . WP_YEARBOOK_CONFIG_TABLE . "  WHERE config_item='style' LIMIT 1" ;
		$result = $wpdb->get_results($query) ;
		$style = $result[0]->config_value ;
	?>
	<form  method="post" enctype="multipart/form-data" action="<?php echo  $site_url . "?page=Yearbook"; ?>">
		<table>
		<tr><td colspan="2"><textarea name="style" cols="50" rows="12"><?php echo $style; ?></textarea></td></tr>
		<tr><td><input  type="submit" value="Mettre le style à jour" name="update_style"></td><td></td></tr>
		<tr><td></td><td style="text-align: right;"><input  type="submit" value="Reset et retour au style par défaut" name="reset_style"></td></tr>
		</table>
	</form>
	<br/>
	
	<?php
}

function yearbook()
{
	global $wpdb ;
	global $page_id ;
	$site_url = $_SERVER["PHP_SELF"] ;
	
	$query = "SELECT * FROM " . WP_YEARBOOK_TABLE . "  ORDER BY promotion DESC LIMIT 1" ;
	$result = $wpdb->get_results($query) ;
	$promo_max = $result[0]->promotion ;
	
	$default_query = $wpdb->prepare(" WHERE promotion = %d ORDER BY name",$promo_max) ;
	$sel=$default_query ;
		
	// Arguments
	if(isset($_GET['order']))
	{
		switch($_GET['order'])
		{
			case promo:
			$sel=" ORDER BY promotion DESC,name" ;
			break ;
			
			case name:
			$sel=" ORDER BY name" ;
			break ;
		}
	}
	
	if(isset($_GET['year']))
	{
		// promotion's year
		if (is_numeric($_GET['year']))
			{
				// control
				if ($_GET['year'] >= $promo_min and $_GET['year'] <= $promo_max or $_GET['year'] == 0)
				{
					$sel=$wpdb->prepare(" WHERE promotion = %d ORDER BY name",(int)$_GET['year']) ;
				}
			}
	}
	
	if(isset($_POST['search']))
	{
		if($_POST['search'] != '')
		{
			$str_accent = stripslashes($_POST['search']);
			
			// Delete accents
			$pattern = Array("/é/", "/è/", "/ê/", "/ë/", "/ç/", "/à/", "/â/", "/ä/", "/î/", "/ï/", "/ù/", "/ô/");
   			// notez bien les / avant et après les caractères
  			$rep_pat = Array("e", "e", "e","e", "c", "a", "a","a", "i", "i", "u", "o");
   			$str_noaccent = preg_replace($pattern, $rep_pat, $str_accent);
			
			$search="%".utf8_encode($str_accent)."%";
			$search_="%".$str_noaccent."%" ;
			
			$sel=$wpdb->prepare(" WHERE name LIKE %s OR forename LIKE %s OR subject LIKE %s OR advisors LIKE %s OR situation LIKE %s OR contact LIKE %s OR name LIKE %s OR forename LIKE %s OR subject LIKE %s OR advisors LIKE %s OR situation LIKE %s OR contact LIKE %s ORDER BY promotion DESC,name",$search,$search,$search,$search,$search,$search,$search_,$search_,$search_,$search_,$search_,$search_);
			//echo $sel ;
		}
	}
	
	
	// Introduction
	
	$query = "SELECT config_value FROM " . WP_YEARBOOK_CONFIG_TABLE . "  WHERE config_item='intro' LIMIT 1" ;
	$result = $wpdb->get_results($query) ;
	$intro = utf8_decode($result[0]->config_value) ;
	
	echo "<p>" . $intro . "</p>" ;
	
	// Display yearbook
	?>
	
	<br/>
	<table style="width: 100%">
	<tr>
	<td style="text-align: center">Tout afficher par : <a href="<?php echo $site_url . "?page_id=" . $page_id . "&order=promo";?>">Année de soutenance</a> - <a href="<?php echo $site_url . "?page_id=" . $page_id . "&order=name" ;?>">Nom</a></td>
	
	<td style="text-align: right">
	<form action="<?php echo  $site_url . "?page_id=" . $page_id ; ?>" method="post">
		<input type="text" name="search" size="20" maxlength="25"/>
		<input type="submit" value="Chercher" />
	</form>
	</td></tr>
	</table>
	
	<br/>
	<div style="text-align: center">
	<?php
	$query = "SELECT * FROM " . WP_YEARBOOK_TABLE . " ORDER BY promotion" ;
	$results = $wpdb->get_results($query) ;
	$date_old = 0 ;
	foreach ($results as $element)
	{
		if ($element->promotion != $date_old)
		{
			echo "<a href=\"" . $site_url . "?page_id=". $page_id ."&year=$element->promotion\">$element->promotion</a> - " ;
			$date_old = $element->promotion ;
		}
	}
	echo "<a href=\"" . $site_url . "?page_id=". $page_id ."&year=0\">Thèses non soutenues</a></div>" ;
		
	
	// Read the data
	$query = "SELECT * FROM " . WP_YEARBOOK_TABLE . $sel ;
	$results = $wpdb->get_results($query) ;
	
	echo "<div id=\"yearbook\">" ;
	// Display the results
	foreach ($results as $element)
	{
		?>
		<br/><br/>
		<table>
			<tr>
				<td colspan="2" class="title"><span class="name"><?php echo utf8_decode($element->name); ?></span> <span class="forename"><?php echo utf8_decode($element->forename) ; ?></span></td>
			</tr>
			
			<tr><td rowspan="6" class="promotion">
			<?php if ($element->promotion == 0) echo "Thèse non soutenue" ;
			else echo $element->promotion ; ?></td>
			
			<td class="item">Sujet de thèse</td></tr>
			<tr><td class="value"><?php echo utf8_decode($element->subject) ; ?></td></tr>

			<tr><td class="item">Encadrants</td></tr>
			<tr><td class="value"><?php echo utf8_decode($element->advisors) ; ?></td></tr>
	
			<tr><td class="item">Situation actuelle</td></tr>
			<tr><td class="value"><?php if ($element->situation == "") echo "Inconnue";
			else echo utf8_decode($element->situation) ; ?></td></tr>
		<?php
		// If superuser, disp email
		if(check_superuser() == true)
		{
			?>
			<tr><td colspan="2" class="email"><b>Email :</b> <a href="mailto:<?php echo utf8_decode($element->name); ?> <?php echo utf8_decode($element->forename); ?> <<?php echo utf8_decode($element->contact); ?>>"><?php echo utf8_decode($element->contact); ?></a></td></tr>		
			<?php
		}
		?>
		</table>
		<?php
	}	
	?>
	</div>
	<?php
	
}

function check_superuser()
{
	global $wpdb ;
	$superuser = false ;
	
	global $current_user;
    get_currentuserinfo();
	
	$user_login = $current_user->user_login ;
	$user_ID = $current_user->ID ;
	
	// if user in logged in, check the superuser list
	if($user_ID)
	{
		$query = "SELECT * FROM " . WP_YEARBOOK_SUPERUSER_TABLE . "  ORDER BY id" ;
		$result = $wpdb->get_results($query) ;
	
		foreach($result as $element)
		{
			if($user_login == $element->login) $superuser = true ;
		}
	}
	return $superuser ;
}


function check_yearbook()
{
	global $wpdb ;
	$wp_yearbook_exists = false ;
	$wp_yearbook_config_exists = false ;
	$wp_yearbook_superuser_exists = false ;
	
	// Does the table exist ?
	$tables = $wpdb->get_results("show tables;");
	
	foreach ( $tables as $table )
    {
      foreach ( $table as $value )
        {
	  		if ( $value == WP_YEARBOOK_TABLE ) $wp_yearbook_exists = true;
	    	if ( $value == WP_YEARBOOK_CONFIG_TABLE ) $wp_yearbook_config_exists = true;
	    	if ( $value == WP_YEARBOOK_SUPERUSER_TABLE ) $wp_yearbook_superuser_exists = true;
        }
    }
        
    if ($wp_yearbook_exists == false)
    {
    	$sql = "CREATE TABLE " . WP_YEARBOOK_TABLE . " (
                                id INT NOT NULL AUTO_INCREMENT ,
                                PRIMARY KEY (id) ,
                                promotion year ,
                                name varchar(20) ,
                                forename varchar(20) ,
                                subject text(500) ,
                                advisors text(100) ,
                                situation text(255) ,
                                contact text(50) 
                        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ";
        echo "Create the table<br/>" ;
        echo $sql . "<br/>" ;                
      	$wpdb->get_results($sql);
    }
    
    if ($wp_yearbook_config_exists == false)
    {
      	$sql = "CREATE TABLE " . WP_YEARBOOK_CONFIG_TABLE . " (
                                config_item VARCHAR(30) NOT NULL ,
                                config_value TEXT NOT NULL ,
                                PRIMARY KEY (config_item)
                        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ";
         echo $sql . "<br/>" ;
     	 $wpdb->get_results($sql);
     	 
     	 // Init
     	$sql = "INSERT INTO ".WP_YEARBOOK_CONFIG_TABLE." SET config_item='intro', config_value=''";
		$wpdb->get_results($sql);
		
		$sql = "INSERT INTO ".WP_YEARBOOK_CONFIG_TABLE." SET config_item='style', config_value=''";
		$wpdb->get_results($sql);
		
		configure_default_style() ;
    }
    
    if ($wp_yearbook_superuser_exists == false)
    {
      	$sql = "CREATE TABLE " . WP_YEARBOOK_SUPERUSER_TABLE . " (
                                id INT NOT NULL AUTO_INCREMENT ,
                                PRIMARY KEY (id) ,
                                login varchar(30)
                        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ";
         echo $sql . "<br/>" ;
     	 $wpdb->get_results($sql);
     	 
     	$sql = "INSERT INTO ".WP_YEARBOOK_SUPERUSER_TABLE." (id,login) VALUES('', 'admin' )" ;
		$wpdb->get_results($sql);
    }
} 

function configure_default_style()
{
	global $wpdb ;
	
	$default_style = "#yearbook {
	width: 600px ;
	margin-left:auto;
	margin-right:auto;
	text-align: center;
	padding-right: 10px ;
}
	
#yearbook table{
	width: 100%;
	border: solid 1px;
	margin-left:auto;
	margin-right:auto;
	text-align: left;
	padding: 1px;
}

#yearbook td.title{
	background-color: #cccccc;
	padding-left: 10px;
}
	
#yearbook td.promotion{
	text-align: center;
	width: 60px;
}
	
#yearbook td.item{
	padding-bottom: 2px;
	padding-top: 5px;
	font-weight: bold;
}
	
#yearbook td.value{
	padding-bottom: 12px;
}
	
#yearbook .name, .forename{
	text-transform: uppercase;
	line-height:1.6;
	padding-bottom: 2px;
	padding-top: 2px;
	font-weight: bold;
	font-size: 1.3em;
}
	
#yearbook .forename{
	text-transform: none;
}

#yearbook td.email{
	padding-left: 66px;
}";
	
	$sql = $wpdb->prepare("UPDATE ".WP_YEARBOOK_CONFIG_TABLE." SET config_value='%s' WHERE config_item='style' LIMIT 1", $default_style) ;
    $wpdb->get_results($sql);
}
  
?>