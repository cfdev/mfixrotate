<?php if (!defined('PLX_ROOT')) exit;

/**
 * Plugin mfixrotate
 *
 * @package cfdev
 * @version	1.0
 * @date	18/12/2015
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
		$String .= '$selectionList["fixrotate"] = "Rotation EXIF";';

		# Récupétion d'une instance de plxMotor
		$String .= '$plxMotor = plxMotor::getInstance();';
		$String .= '$plxPlugin = $plxMotor->plxPlugins->getInstance("mfixrotate");';
	
		# Récupération des var post
		$String .= 'if(isset($_POST["selection"]) AND ((!empty($_POST["btn_ok"]) AND $_POST["selection"]=="fixrotate")) AND isset($_POST["idFile"])) {';
		$String .= '	if( $plxPlugin->rotateEXIF($_POST["idFile"]) ) {';
		$String .= '		$plxMedias->makeThumbs($_POST["idFile"], $plxAdmin->aConf["miniatures_l"], $plxAdmin->aConf["miniatures_h"]);';
		$String .= '	} ';
		$String .= '} ';
		$String .= ' ?>';	
	
		echo  $String;
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
		ini_set('memory_limit', '64M');
		foreach($files as $file) {
			$filename = $this->imgFullPath . "/" .$file;
			
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
				return true;
			}
			else {
				return false;
			}
		}
	}
}
?>

