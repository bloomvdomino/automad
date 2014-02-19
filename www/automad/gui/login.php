<?php
/*
 *	                  ....
 *	                .:   '':.
 *	                ::::     ':..
 *	                ::.         ''..
 *	     .:'.. ..':.:::'    . :.   '':.
 *	    :.   ''     ''     '. ::::.. ..:
 *	    ::::.        ..':.. .''':::::  .
 *	    :::::::..    '..::::  :. ::::  :
 *	    ::'':::::::.    ':::.'':.::::  :
 *	    :..   ''::::::....':     ''::  :
 *	    :::::.    ':::::   :     .. '' .
 *	 .''::::::::... ':::.''   ..''  :.''''.
 *	 :..:::'':::::  :::::...:''        :..:
 *	 ::::::. '::::  ::::::::  ..::        .
 *	 ::::::::.::::  ::::::::  :'':.::   .''
 *	 ::: '::::::::.' '':::::  :.' '':  :
 *	 :::   :::::::::..' ::::  ::...'   .
 *	 :::  .::::::::::   ::::  ::::  .:'
 *	  '::'  '':::::::   ::::  : ::  :
 *	            '::::   ::::  :''  .:
 *	             ::::   ::::    ..''
 *	             :::: ..:::: .:''
 *	               ''''  '''''
 *	
 *
 *	AUTOMAD CMS
 *
 *	Copyright (c) 2014 by Marc Anton Dahmen
 *	http://marcdahmen.de
 *
 *	Licensed under the MIT license.
 */

/**
 *	The Automad GUI login Page.
 */


define('AUTOMAD', true);
require 'elements/base.php';


if ($_POST) {
	
	$username = $_POST['username'];
	$password = $_POST['password'];
	$accounts = unserialize(file_get_contents(AM_FILE_ACCOUNTS));
	
	if (isset($accounts[$username]) && $G->passwordVerified($password, $accounts[$username])) {
		
		$_SESSION['username'] = $username;
		header('Location: http://' . $_SERVER['SERVER_NAME'] . AM_BASE_URL . '/automad');
		die;
		
	} else {
		
		$G->modalDialogContent = 'Invalid username or password!';
		
	}
		
}


$G->guiTitle = 'Log In';
$G->element('header_400');


?>

<div class="box">

	<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
		<input class="item bg input" type="text" name="username" placeholder="Username" />
		<input class="item bg input" type="password" name="password" placeholder="Password" />
		<input class="item bg button" type="submit" value="Log In" />
	</form>

</div>

<?php


$G->element('footer');


?>