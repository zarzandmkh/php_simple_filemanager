<?php 
session_start();
error_reporting(E_ALL);
header('Content_type:text/html; charset=utf-8');
mb_internal_encoding('utf-8');

define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('ROOT_DIR', dirname(__FILE__));
define('SELF_SCRIPT', $_SERVER['SCRIPT_NAME']);																	//application script file

$is_windows = stripos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows')?true:false;							//check if windows
$errors = isset($_GET['errors'])&&!empty($_GET['errors'])?unserialize($_GET['errors']):array();
$text_extensions = array('txt', 'php', 'doc', 'docx', 'tpl', 'odt');											//text file extensions (editable)
$get_query = !empty($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:false;									//current get request
$prohibited_chars = array('\\', '/', ':', '*', '?', '"', '>', '<', '|', '+', '%', '!', '@', '&');				//prohibited characters for naming files
$image_extansions = array('jpg', 'jpeg', 'png', 'gif');

//success alert
if(isset($_GET['success']) && !empty($_GET['success'])){								
	switch ($_GET['success']){
		case 'f_upl':
			$success = 'File successfully uploaded';
			break;
		case 'upk':
		$success = 'Files are succesfully unzipped';
		break;
		default:
			$success = 'Success!';
			break;
	}
}

//encoding chage(win-1251 -> utf-8  and back)
function win_to_utf($var, $utf_to_win = false){
	if(stripos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows')){
		if($utf_to_win){
			return iconv('utf-8', 'windows-1251', $var);
		}else{
			return iconv('windows-1251', 'utf-8',  $var);
		}
	}else{
		return $var;
	}
}


/*//function for debugging
function out($variable){
	echo'<pre>';
	print_r($variable);
	echo'</pre>';
}*/

//display of file size unit
function out_size($size_in_b){
    if($size_in_b / pow(2,30) >= 1){
        return round($size_in_b / pow(2,30), 1) . ' gb';
    }else if($size_in_b / pow(2,20) >= 1){
        return round($size_in_b / pow(2,20), 2) . ' mb';
    }else if($size_in_b / pow(2,10) >= 1){
        return round($size_in_b / pow(2,10), 2) . ' kb';
    }else{
        return $size_in_b . ' б';
    }
}

//recursive deleting of tree
function delTree($dir) { 
	$files = array_diff(scandir($dir), array('.','..')); 
	foreach ($files as $file) { 
	  (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
	} 
	return rmdir($dir); 
}

//current dir url
if(!isset($_SESSION['path'])){$_SESSION['path'] = ROOT_DIR;}

if(isset($_GET['name'])){//element chosing
	$n =  win_to_utf(trim($_GET['name']), true);
	if($_GET['name'] == '.' && $_SESSION['path'] != ROOT_DIR){
		$_SESSION['path'] = mb_strrchr($_SESSION['path'], '/', true);
	}else if(is_dir($_SESSION['path'] . '/' . $n)){//rebuild dir url if folder is chosen
		$_SESSION['path'] .= '/' . $n; 
	}else{
		//nothing to do if not folder
	}
}else{
	// $_SESSION['path'] = ROOT_DIR;
}


$path = $_SESSION['path'];							//current dir url
$url = str_replace(ROOT_DIR, SITE_URL, $path);		//current url
$dir = array_diff(scandir($path), array('..'));		//folders and files list of current directory

$dirs = array();									//foldres
$files = array();									//files
$others = array();									//unknown extensions

foreach ($dir as $key => &$value) {
	if($is_windows){$value = win_to_utf($value);}
	if($value != str_replace('/', '', $_SERVER['PHP_SELF'])){//skipping application file
		if(is_dir($path . '/' .  win_to_utf($value, true) )){
			$dirs[] = $value;
		}elseif(is_file($path . '/' . win_to_utf($value, true) )){
			$files[] = $value;
		}else{
			$others[] = $value;
		}
	}
}
//sorting first folders then files
$dir = array_merge($dirs, $files, $others);

//renaming
if(isset($_POST['rename']) && !isset($_POST['cancel'])){
	$old_name = trim(htmlentities(strip_tags($_POST['old_name'])));
	$new_name = trim(htmlentities(strip_tags($_POST['new_name'])));
	if(mb_strlen($new_name) == 0){$errors[] = 'Name is not filled';}
	foreach ($prohibited_chars as $key => $char) {
		if(mb_stristr($new_name, $char)){
			$errors[] = implode(' ', $prohibited_chars) . ' chars are prohibited in folder or file names';
			break;
		}
	}
	if(count($errors) === 0){
		$new_name = win_to_utf($new_name, true);
		$old_name = win_to_utf($old_name, true);;
		rename($path . '/' . $old_name, $path . '/' . $new_name);
		$url = SITE_URL . '?' . str_replace('rename', '', $get_query);
		header("Location:$url");	
	}
}

//file upload
if(isset($_POST['upload'])){
	if($_FILES['file']['error'] == 4){
		$errors[] = 'File is not chosen';
	}
	if(count($errors) == 0 && $_FILES['file']['error'] === 0){
		if(move_uploaded_file($_FILES['file']['tmp_name'], $path . '/' . win_to_utf($_FILES['file']['name'], true)) ){
			$url = SITE_URL . SELF_SCRIPT . ($get_query?'?'.$get_query.'&success=f_upl':'?success=f_upl');
			header("Location:$url");
		}else{
			$errors[] = 'Error while uploading file: move_uploaded_file()';
		}
	}else{
		$errors[] = 'Error while uploading file: move_uploaded_file()';
	}
}

//file deleting
if(isset($_GET['delete']) && !empty($_GET['delete'])){
	$f = trim(htmlentities(strip_tags($_GET['delete'])));
	$item_to_delete = $path . '/' . win_to_utf($f, true);
	if(is_file($item_to_delete)){
		$result_delete = unlink($item_to_delete);
	}else if(is_dir($item_to_delete)){
		$result_delete = delTree($item_to_delete);//recursive deleting of whole folder
	}else{
		$result_delete = false;
	}

	if($result_delete && count($errors) === 0){
		$url = SITE_URL . SELF_SCRIPT . '?' . str_replace('delete', '', $get_query);
		header("Location:$url");
	}else{
		$errors[] = 'Ошибка при удалении';
	}
}

//text files editing
if(isset($_GET['edit_item']) && !empty($_GET['edit_item'])){
	$e = trim(htmlentities(strip_tags($_GET['edit_item'])));
	$item_to_edit = $path . '/' . win_to_utf($e, true);
	$file_content = is_file($item_to_edit)?file_get_contents($item_to_edit):'';
}
if(isset($_POST['save_changes'])){
	$text = htmlentities($_POST['text']);
	if(file_put_contents($item_to_edit, $text)){
		$url = SITE_URL . '?edit_item=' . $e;
		header("Location:$url");
	}else{
		$errors[] = 'Ошибка при сохранении';
	}
}
if(isset($_POST['discard_changes'])){//cancel and close editor
	unset($_GET['edit_item']);
	$url = SITE_URL . SELF_SCRIPT . '?' . str_replace('edit_item', '', $get_query);
	header("Location:$url");
}


//zip/unzip
if(isset($_POST['archivate'])){
	if(isset($_POST['files_to_archiving']) && count($_POST['files_to_archiving']) > 0){
		$zip = new ZipArchive;
		$zipname = time() . '_' . rand();
		if ($zip->open($path . '/' . $zipname . '.zip', ZipArchive::CREATE) === true){
			foreach ($_POST['files_to_archiving'] as $ky => $f_arch) {
				if(is_file($path . '/' . win_to_utf($f_arch, true)))$zip->addFile($path . '/' . $f_arch, $f_arch);
			}
			if(!$zip->close()){$errors[] = 'Ошибка при создании архива';};
			if(count($errors) == 0){
				$url = SITE_URL . SELF_SCRIPT . '?rename_item=' . $zipname . '.zip';
				header("Location:$url");
				unset($_SESSION['for_arch']);
			}

		}else{
			$errors[] = 'Ошибка при создании архива';
		}
	}else{
		$errors[] = 'Ошибка при создании архива';
	}
	
}

if(isset($_GET['unpack']) && !empty($_GET['unpack'])){
	$archive = win_to_utf(htmlentities(strip_tags($_GET['unpack'])) ) ;
	$zip_u = new ZipArchive;
    $zip_u->open($path . '/' . $archive);
    $zip_u->extractTo($path);
    if(!$zip_u->close()){$errors[] = 'Ошибка при извлечении файлов из архива';}
    if(count($errors) == 0){
    	$url = SITE_URL . SELF_SCRIPT . '?success=upk';
		header("Location:$url");
    }
}

//zip cancelling
if(isset($_GET['cancel_arch'])){
	unset($_GET['cancel_arch']);
	$url = SITE_URL . SELF_SCRIPT/*. '?' . str_replace(['?arch=1', '$arch=1', '?cancel_arch=1', '&cancel_arch=1'], '', $get_query)*/;
	header("Location:$url");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>File manager</title>
	<style>
		#wrap {width:960px; padding:30px; margin:0 auto; position: relative;}
		a{text-decoration: none; color:black;}
		input[name="new_name"]{height:18px;}
		a:visited{color:black;}
		a.back{color:blue; font-style: italic;}
		table, tr, td, th{border:1px solid black; border-collapse: collapse;padding:10px;}
		textarea{overflow: scroll; border-radius: 2px; box-shadow: 0px 0px 10px 0px grey; padding:10px;}
		.main-table{clear: both; width: 100%;}
		.add-file, .editor{margin:20px 5px;}
		.errors{padding:5px 10px; background: #ff4444; border-radius: 2px; box-shadow: 0px 0px 10px 0px rgba(184,0,15,0.7);}
		.success{padding:20px; background: #00C851; border-radius: 2px; box-shadow: 0px 0px 10px 0px rgba(0,150,23,0.7);}
		.arch_button{margin: 5px 0; float:right;}
		.archivate{margin: 5px 0; }
		#naming-archive{border:1px solid black; border-radius: 2px; position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); padding:20px; background: #ffbb33; box-shadow: 0px 0px 14px 2px rgba(0,0,0,0.75);}
		#naming-archive button {margin-right: 5px;}
		table a:hover{text-decoration: underline;}
		table a:not(:last-child):after{content: " | ";}
		.archiving-buttons{padding:20px; background: yellow; border: 1px solid black; position: fixed; top:5px; right: 20%; border-radius: 2px;}
	</style>
		
</head>
<body>
	<div id="wrap">
		<h1>File manager</h1>
		<?php if (count($errors) > 0){?>
			<div class="errors">
				<?php foreach($errors as $error){?>
				<p><?=$error;?></p>
				<?php } ?>
			</div>
		<?php } ?>
		<?php if(isset($success)){?>
			<div class="success"><?=$success;?></div>
		<?php } ?>
		<?php if(isset($_GET['edit_item']) && !empty($_GET['edit_item'])){ ?>
		<hr>
		<form method="POST" class="editor">
			<h4>Редактор тектовых файлов</h4>
			<p><?=$_GET['edit_item']?></p>
			<textarea id="" cols="100" rows="20" name="text"><?=$file_content;?></textarea>
			<br><br><input type="submit" value="Save" name="save_changes">
			<input onclick="return confirm('Are you sure?All ansaved data wil be deleted');" type="submit" value="Close" name="discard_changes">
		</form>
		<hr>
		<?php } ?>
		<form method="POST" enctype="multipart/form-data" class="add-file">
			<input type="file" name="file">
			<input type="submit" value="Upload file to current folder" name="upload">
		</form>
		<p><?=str_replace(['. /', './'], '', str_replace(['\\', '/'], ' / ', win_to_utf($path) ));?></p>

		<button class="arch_button"><a href="?for_arch=1">Chose files to zip</a></button>

		<table class="main-table">
			<tr>
				<th>Name</th>
				<th>Size</th>
				<th>Actions</th>
			</tr>
			<form method="POST">
			<?php foreach ($dir as $key => $item) {?>
				<?php if($item != '.' || $path != ROOT_DIR){?>
				<tr>
					<td>
						<?php if(isset($_GET['rename_item']) && $_GET['rename_item'] == $item && !isset($_POST['cancel'])){?>
							<form>
								<input type="text" name="new_name" value="<?=$item;?>" autofocus="atofocus">
								<input type="hidden" name="old_name" value="<?=$item;?>">
								<input type="submit" value="&#x1f5d9;" title="Cancel" name="cancel">
								<input type="submit" value="&#10003;" title="Apply" name="rename">
							</form>
						<?php }else if(in_array(strtolower(pathinfo($path . '/' . $item, PATHINFO_EXTENSION)), $image_extansions)){?>
						<a href="<?=$url.'/'.$item;?>" target="_blank">&#x1f5b9;<?=$item;?></a>
						<?php }else{?>
						<a href="?name=<?=$item;?>" <?=$item=='.'?'class="back"':'';?> >
							<?php if(isset($_GET['for_arch']) && $item != '.' && is_file($path . '/' . win_to_utf($item, true))){?>
							<input type="checkbox" name="files_to_archiving[<?=$key;?>]" value="<?=$item;?>" <?=isset($_POST['files_to_archiving'][$key])?'checked':'';?>>
							<?php } ?>
							<?=is_dir($path . '/' . win_to_utf($item, true))&&$item!='.'?'&#x1f4c1;':
							(is_file($path . '/' . win_to_utf($item, true))?'&#x1f5b9;':'');?>
							<?=$item=='.'?'&#x21e6; назад':$item;?>
						</a>
						<?php } ?>
					</td>
					<td>
						<?php if($item != '.'){?>
						<?=is_file($path . '/' . win_to_utf($item, true))?out_size(filesize($path . '/' . win_to_utf($item, true))):'___';
						}?>	
					</td>
					<td>
						<?php if($item != '.'){?>
							<?php if(is_file($path . '/' . win_to_utf($item, true))){?>
								<a href="<?=$url . '/' . $item;?>" download>Скачать</a>
							<?php } ?>
							<a href="<?=$get_query?'?'.$get_query.'&rename_item='.$item:'?rename_item='.$item;?>">Rename</a> 
							<a onclick="return confirm('Вы уверены?');" href="<?=$get_query?'?'.$get_query.'&delete='.$item:'?delete='.$item;?>">Delete</a>
							<?php if(in_array(strtolower(pathinfo($path . '/' . $item, PATHINFO_EXTENSION)), $text_extensions)){?> 
							<a href="<?=$get_query?'?'.$get_query.'&edit_item='.$item:'?edit_item='.$item;?>">Edit</a>
							<?php } ?>
							<?php if(strtolower(pathinfo($path . '/' . $item, PATHINFO_EXTENSION)) == 'zip'){?> 
							<a href="<?=$get_query?'?'.$get_query.'&unpack='.$item:'?unpack='.$item;?>">Unzip</a>
							<?php } ?>
						<?php } ?>
					</td>
				</tr>
			<?php } 
				}?>
		</table>
		<?php if(isset($_GET['for_arch'])){ ?>
		<div class="archiving-buttons">
			<button class="archivate"><a href="<?=SITE_URL  . '?cancel_arch=1';?>">Cancel</a></button>
			<input class="archivate" type="submit" value="Создать архив" name="archivate" >
		</div>
		<?php } ?>
	</form>
	</div>
</body>
</html>