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


namespace Core;


defined('AUTOMAD') or die('Direct access not permitted!');


/**
 * 	All ajax requests regarding a page's data file get processed here.
 *	Basically that means "Saving, Renaming & Redirecting" as the first option and "Loading" as the second option.
 *
 *	When "$_POST['data']" exists, that means, that a form with "edited" page information got submitted and the data gets processed to be written into the data file.
 *	In that case, this handler either returns a redirect URL (for reloading the page, in case it got renamed) or an error message. NO (!) form data is submitted back, since
 *	The form already exists on the "client side".
 *
 *	When only "$_POST['url']" got submitted, that means, the the form on the "client side" is still empty and therefore it must be an initial page loading request, which then will return 
 *	the page's data as the form HTML.	
 */


// Array for returned JSON data.
$output = array();


// Verify page's URL - The URL must exist in the site's collection.
if (isset($_POST['url']) && array_key_exists($_POST['url'], $this->collection)) {

	
	$url = $_POST['url'];
	
	
	// The currently edited page.
	$P = $this->collection[$url];
	
	
	// If the posted form contains any "data", save the form's data to the page file.
	if (isset($_POST['data'])) {
	
	
		$data = $_POST['data'];
	
	
		// A title is required for building the page's path.
		// If there is no title provided, an error will be returned instead of saving and moving the page.
		if ($data['title']) {
	
	
			// Remove empty data.
			// Needs to be done here, to be able to simply test for empty title field.
			$data = array_filter($data);
		
		
			// Set hidden parameter within the $data array. 
			// Since it is a checkbox, it must get parsed separately.
			if (isset($_POST['hidden'])) {
				$data['hidden'] = 1;
			}
	
	
			// The theme and the template get passed as theme/template.php combination separate form $_POST['data']. 
			// That information has to be parsed first and "subdivided".

			// Get correct theme name.
			// If the theme is not set and there is no slash passed within 'theme_template', the resulting dirname is just a dot.
			// In that case, $data['theme'] gets removed (no theme - use site theme). 
			if (dirname($_POST['theme_template']) != '.') {
				$data['theme'] = dirname($_POST['theme_template']);
			} else {
				unset($data['theme']);
			}
	
	
			// Build file content to be written to the txt file.
			$pairs = array();

			foreach ($data as $key => $value) {
				$pairs[] = $key . AM_PARSE_PAIR_SEPARATOR . ' ' . $value;
			}

			$content = implode("\r\n\r\n" . AM_PARSE_BLOCK_SEPARATOR . "\r\n\r\n", $pairs);
	

			// Delete old (current) file, in case, the template has changed.
			unlink($this->pageFile($P));


			// Build the path of the data file by appending the basename of 'theme_template' to $page->path.
			$newPageFile = AM_BASE_DIR . AM_DIR_PAGES . $P->path . str_replace('.php', '', basename($_POST['theme_template'])) . '.' . AM_FILE_EXT_DATA;
	
	
			// Save new file within current directory, even when the prefix/title changed. 
			// Renaming/moving is done in a later step, to keep files and subpages bundled to the current text file.
			$old = umask(0);
			file_put_contents($newPageFile, $content);
			umask($old);
	
	
			// If the page is not the homepage, 
			// rename the page's directory including all children and all files, after saving according to the (new) title and prefix.
			// $this->movePage() will check if renaming is needed, and will skip moving, when old and new path are equal.
			if ($url != '/') {
	
				if (!isset($_POST['prefix'])) {
					$prefix = '';
				} else {
					$prefix = $_POST['prefix'];
				}

				$newPagePath = $this->movePage($P->path, dirname($P->path), $prefix, $data['title']);
	
			} else {
			
				// In case the page is the home page, the path is just '/'.
				$newPagePath = '/';
			
			}
	

			// Clear the cache to make sure, the changes get reflected on the website directly.
			$C = new Cache();
			$C->clear();
	

			// Rebuild Site object, since the file structure might be different now.
			$S = new Site(false);
			$collection = $S->getCollection();

	
			// Find new URL.
			foreach ($collection as $key => $page) {
		
				if ($page->path == $newPagePath) {
				
					// Just return a redirect URL (might be the old URL), to also reflect the possible renaming in all the GUI's navigation.
					$output['redirect'] = '?context=edit_page&url=' . urlencode($key);
					break;
				
				}
		
			}
	
	
		} else {
		
			// If the title is missing, just return an error.
			$output['error'] = 'You can not save a page without a title.';
		
		}
	
	
	} else {
		
		// If only the URL got submitted, 
		// get the page's data from its .txt file and return a form's inner HTML containing these information.
		
		// Get page's data.
		$data = Parse::textFile($this->pageFile($P));
	
		// These keys are always part of the form and have to be normalized/created.
		$standardKeys = array(AM_KEY_TITLE, AM_KEY_TAGS, AM_KEY_THEME, AM_KEY_URL, AM_KEY_HIDDEN);

		foreach ($standardKeys as $key) {
			if (!isset($data[$key])) {
				$data[$key] = false;
			}
		}

		// Set title, in case the variable is not set (when editing the text file in an editor and the title wasn't set correctly)
		if (!$data[AM_KEY_TITLE]) {
			$data[AM_KEY_TITLE] = basename($P->url);
		}
	
		// Start buffering the HTML.
		ob_start();

		?>

			<input type="hidden" name="url" value="<?php echo $P->url; ?>" />

			<div class="form-group col-md-12">
				<label for="input-data-title" class="text-muted"><?php echo ucwords(AM_KEY_TITLE); ?></label>
				<input id="input-data-title" class="form-control input-lg" type="text" name="data[<?php echo AM_KEY_TITLE; ?>]" value="<?php echo $data[AM_KEY_TITLE]; ?>" onkeypress="return event.keyCode != 13;" placeholder="Required" required />
			</div>

			<div class="form-group col-md-12">
				<label for="input-data-tags" class="text-muted"><?php echo ucwords(AM_KEY_TAGS); ?> (comma separated)</label>
				<input id="input-data-tags" class="form-control input-sm" type="text" name="data[<?php echo AM_KEY_TAGS; ?>]" value="<?php echo $data[AM_KEY_TAGS]; ?>" onkeypress="return event.keyCode != 13;" />
			</div>

			<div class="form-group col-md-4">
				<label for="input-prefix" class="text-muted">Prefix (Order in Navigation)</label>
				<input id="input-prefix" class="form-control input-sm" type="text" name="prefix" value="<?php echo $this->extractPrefixFromPath($P->path); ?>" <?php if ($P->path == '/') { echo 'disabled'; } ?> onkeypress="return event.keyCode != 13;" />
			</div>

			<div class="col-md-6">
				<?php echo $this->templateSelectBox('theme_template', 'theme_template', $data[AM_KEY_THEME], $P->template); ?>
			</div>
			
			<div class="form-group col-md-2">
				<label for="input-hidden" class="text-muted">Hide page</label>
				<input id="input-hidden" class="form-control input-sm" type="checkbox" name="<?php echo AM_KEY_HIDDEN; ?>"<?php 

					// Check checkbox
					if (isset($data[AM_KEY_HIDDEN]) && $data[AM_KEY_HIDDEN] && $data[AM_KEY_HIDDEN] != 'false') {
						echo ' checked';
					}; 

					// Disable for home page
					if ($P->path == '/') { 
						echo ' disabled'; 
					}

				?> />

			</div>

			<div class="form-group col-md-12">
				<label for="input-redirect" class="text-muted">Redirect URL</label>
				<input id="input-redirect" class="form-control input-sm" type="text" name="data[<?php echo AM_KEY_URL; ?>]" value="<?php echo $data[AM_KEY_URL]; ?>" placeholder="http://" onkeypress="return event.keyCode != 13;" />
			</div>

			<div id="automad-custom-variables">
				<?php

				foreach ($data as $key => $value) {
					
					// Only user defined custom variables.
					// Standard vars are processed separately
					if (!in_array($key, $standardKeys)) {
						echo 	'<div class="form-group col-md-12">' . 
							'<label for="input-data-' . $key . '" class="text-muted">' . ucwords($key) . '</label>' .
							'<button type="button" class="close automad-remove-parent">&times;</button>' .
							'<textarea id="input-data-' . $key . '" class="form-control input-sm" name="data[' . $key . ']" rows="10">' . $value . '</textarea>' .
							'</div>';	
					}
					
				}

				?>
			</div>

			<div class="pull-right">
				<div class="btn-group col-md-12">
					<button type="button" class="btn btn-default" data-toggle="modal" data-target="#automad-add-variable-modal"><span class="glyphicon glyphicon-plus"></span> Add Variable</button>
					<a href="" class="btn btn-default"><span class="glyphicon glyphicon-remove"></span> Discard Changes</a>
					<button type="submit" class="btn btn-success" data-loading-text="Saving Changes ..."><span class="glyphicon glyphicon-ok"></span> Save Changes</button>
				</div>
			</div>

			<!-- Add Variable Modal -->	
			<div id="automad-add-variable-modal" class="modal fade">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
							<h4 class="modal-title">Add Another Custom Variable</h4>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="automad-add-variable-name" class="text-muted">Variable Name</label>
								<input type="text" class="form-control" id="automad-add-variable-name" />
							</div>	
						</div>
						<div class="modal-footer">
							<div class="btn-group">
								<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
								<button type="button" class="btn btn-primary" id="automad-add-variable-button">Add</button>
							</div>
						</div>
					</div>
				</div>
			</div>

		<?php	
	
	
		// Save buffer to JSON array.
		$output['html'] = ob_get_contents();
		ob_end_clean();
			
		
	}
	
	
} else {
	
	$output['error'] = 'Error! Page not found!';
	
}


echo json_encode($output);


?>