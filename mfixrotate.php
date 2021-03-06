﻿<?php if (!defined('PLX_ROOT')) exit;

/**
 * Plugin mfixrotate
 *
 * @package cfdev
 * @version	1.1
 * @date	01/02/2017
 * @author	Cyril Frausti
 * @url		http://cfdev.fr
 **/
class mfixrotate extends plxPlugin {
	
	public $imgFullPath;
	public $imgPath;
	
	public function __construct($default_lang) {
		# appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($default_lang);
		
		# Récupération d'une instance de plxMotor
		$plxMotor = plxMotor::getInstance();
		#init path
		$this->imgFullPath = isset($plxMotor->aConf['medias']) ? plxUtils::getRacine().$plxMotor->aConf['medias'] : plxUtils::getRacine().$plxMotor->aConf['images'];
		$this->imgPath = isset($plxMotor->aConf['medias']) ? $plxMotor->aConf['medias'] : $plxMotor->aConf['images'];

		# PROFIL_ADMIN , PROFIL_MANAGER , PROFIL_MODERATOR , PROFIL_EDITOR , PROFIL_WRITER
        # Accès au menu admin réservé au profil administrateur
        $this->setAdminProfil(PROFIL_ADMIN, PROFIL_MANAGER);
        # droits pour accèder à la page config.php du plugin
        $this->setConfigProfil(PROFIL_ADMIN);
		
		# Déclaration des hooks
		$this->addHook('AdminMediasTop', 'AdminMediasTop');
	}
	
	/**
	 * Hook qui injecte du code php dans la partie gestion des medias
	 *
	 * @param	rien
	 * @return  echo string
	 * @author	cfdev
	 **/
	public function AdminMediasTop() {
		$String = '<?php ';
		$String .= '$selectionList["--"] = "-----"; ';
		//$String .= '$selectionList["fixrotate"] = "Rotation EXIF";';
		$String .= '$selectionList["rotateleft"] = "Rotation gauche 90°";';
		$String .= '$selectionList["rotateright"] = "Rotation droite 90°";';
				
		# Récupétion d'une instance de plxMotor
		$String .= '$plxMotor = plxMotor::getInstance();';
		$String .= '$plxPlugin = $plxMotor->plxPlugins->getInstance("mfixrotate");';
	
		# Récupération des var post pour la rotation manuelle
		$String .= ' if(isset($_POST["selection"]) AND ((!empty($_POST["btn_ok"]) AND $_POST["selection"]=="rotateleft")) AND isset($_POST["idFile"]) AND isset($_POST["folder"])) {';
		$String .= '	$plxPlugin->rotateM($_POST["idFile"], $_POST["folder"], 90) ;';	
		$String .= '} ';
		
		$String .= 'else if(isset($_POST["selection"]) AND ((!empty($_POST["btn_ok"]) AND $_POST["selection"]=="rotateright")) AND isset($_POST["idFile"]) AND isset($_POST["folder"])) {';
		$String .= '	$plxPlugin->rotateM($_POST["idFile"], $_POST["folder"], -90) ;';
		$String .= '} ';
		$String .= ' ?>';

	
		echo  $String;
	}
	
		/**
	 * Méthode qui tourne l'image manuellement
	 *
	 * @param	files	liste des fichier à tourner
	 * @return  true if rotate
	 * @author	cfdev
	 **/
	public function rotateM($files, $folder, $deg) {		
		// ++Limit PHP
		//ini_set('memory_limit', '64M');
		$ret = false;
		if($folder ==".")$folder = "";
		foreach($files as $file) {
			$filename = $this->imgFullPath . $folder .$file;
			// rotate img
			if($deg){
				
				# recupère les info qu'on a une image
				$info = getimagesize($filename);
				if (!$info) return false;
				
				# Création de l'image
				switch ($info['mime']) {
					case 'image/jpeg':
						$img = imagecreatefromjpeg($filename) or die('<div class="alert red">Error rotateM::imagecreatefromjpeg ...</div> '.$filename);
					break;
					case 'image/png':
						$img = imagecreatefrompng($filename) or die('<div class="alert red">Error rotateM::imagecreatefrompng ...</div> '.$filename);
					break;
					case 'image/gif':
						$img = imagecreatefromgif($filename) or die('<div class="alert red">Error rotateM::imagecreatefromgif ...</div> '.$filename);
					break;
					default:
						$ret = false;
					break;
				}
		
				# Rotation de l'image
				$rotate = imagerotate($img, $deg, 0) or die('<div class="alert red">Error rotateM::imagerotate</div> '.$filename);
				imagejpeg($rotate, "../../".$this->imgPath.$folder.$file) or die('<div class="alert red">Erreur lors de l\'enregistrement de l\'image</div> '.$file);
				echo '<div class="alert green"> Rotation réussi ! - '.$file.' > <a href="'.$filename.'" target="_blank">Voir l\'image</a></div>';
				
				# regeneration des miniatures
				$thumName = plxUtils::thumbName($file);
				plxUtils::makeThumb($filename, "../../".$this->imgPath.$folder.$thumName, $plxAdmin->aConf["miniatures_l"], $plxAdmin->aConf["miniatures_h"]);
				# Pour le gestionnaire de média
				plxUtils::makeThumb($filename, "../../".$this->imgPath.'.thumbs/'.$folder.$file, 48, 48);
				
				$ret = true;
			}
			else{
				$ret = false;
			}
		}
		return $ret;
	}
	
	/**
	 * Méthode qui tourne l'image en fonction des données EXIF
	 *
	 * @param	files	liste des fichier à tourner
	 * @return  true if rotate
	 * @author	cfdev
	 **/
	public function rotateEXIF($files) {
		// ++Limit PHP
		/*//ini_set('memory_limit', '64M');
		foreach($files as $file) {
			$filename = $this->imgFullPath .$file;
			
			$data = exif_read_data($filename, ANY_TAG, true);		
			//plxUtils::debug($data);

			if(!isset($data['IFD0']['Orientation'])) {
				echo '<div class="alert blue">Données <strong>EXIF</strong> non présentes!</div>';
				return false;
			}
			switch($data['IFD0']['Orientation']) {
				case 3:
					echo $deg = 180; break;
				case 6:
					echo $deg = 90; break;
				case 8:
					echo $deg = 270; break;
				default:
					echo '<div class="alert orange"> Rotation non prise en charge!</div>';break;
			}
			// rotate img
			if($deg){
				$img = imagecreatefromjpeg($filename) or die('<div class="alert red">Error rotateEXIF::imagecreate (JPEG only)...</div> '.$filename);
				$rotate = imagerotate($img, $deg, 0) or die('<div class="alert red">Error rotateEXIF::imagerotate</div> '.$filename);
				imagejpeg($rotate, "../../".$this->imgPath.$file) or die('<div class="alert red">Erreur lors de l\'enregistrement de l\'image</div> '.$file);
				echo '<div class="alert green"> Rotation successfull!</div>';
			}
		}*/
	}
}
?>

