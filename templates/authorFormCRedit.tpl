{**
 * templates/authorFormOrcid.tpl
 *
 * Copyright (c) 2017-2019 University Library Heidelberg
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Extensions to Submissioni Metadata Author add/edit Form
 *
 *}
{fbvFormSection list="true" title="CRedit Role" translate=false}
	{foreach $creditRoles key="uri" item="term"}		
		{fbvElement type="checkbox" 
		label=$term|escape 
		id="creditRoles[]" 
		value=$uri
		checked=in_array($uri, $authorCreditRoles)
		translate=false}
	{/foreach}
{/fbvFormSection}
