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
{fbvFormSection list="true" label="plugins.generic.credit.contributorRoles" translate=true description="plugins.generic.credit.contributorRoles.description"}
	{foreach $creditRoles key="uri" item="i18n"}		
		{fbvElement type="checkbox" 
		label=$i18n['name']|escape 
		id="creditRoles[]" 
		value=$uri
		checked=in_array($uri, $authorCreditRoles)
		translate=false}
	{/foreach}
{/fbvFormSection}
