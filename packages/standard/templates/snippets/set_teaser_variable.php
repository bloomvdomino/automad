<?php defined('AUTOMAD') or die('Direct access not permitted!'); ?>
<# Reset variable to false in case there is no match. #>
<@~ set { :teaser: false } @>
<# Try to get first paragraph from content. #>
<@~ if not @{ textTeaser } @>
	<@~ set { :teaser: 
		@{ +main | 
			def (@{ textTeaser | markdown }) | 
			def (@{ text | markdown }) |
			findFirstParagraph 
		}
	} @>
<@~ else @>
	<@~ set { :teaser: 
		@{ textTeaser | markdown | replace ('/\r\n|\r|\n/','') }
	} @>
<@~ end @>
