<?php

/**
 * @file CreditPlugin.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class CreditPlugin
 * @brief Support for the NISO CRediT contributor credit vocabulary.
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('plugins.generic.credit.classes.form.CreditSettingsForm');

use PKP\components\forms\FieldOptions;

use \DOMDocument;

use PKP\config\Config;
use PKP\core\Registry;
use PKP\author\maps\Schema;
use APP\author\Author;

// use APP\plugins\generic\credit\classes\form\CreditSettingsForm;

class CreditPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            $contextId = ($mainContextId === null) ? $this->getCurrentContextId() : $mainContextId;
            if ($this->getEnabled($mainContextId)) {

                // HookRegistry::register('Form::config::before', [$this, 'addCreditRoles']);
                HookRegistry::register('authorform::display', array($this, 'handleAuthorFormDisplay'));

        		// Send email to author, if the added checkbox was ticked
		        HookRegistry::register('authorform::execute', array($this, 'handleAuthorFormExecute'));                
                
                // Add field to author Schema
                HookRegistry::register('Schema::get::author', function ($hookName, $args) {
                    $schema = $args[0];
                    $schema->properties->creditRoles = json_decode('{
                        "type": "array",
                        "validation": [
                            "nullable"
                        ],
                        "items": {
                            "type": "string"
                                    }
                                }');

                });
                if ($this->getSetting($contextId, 'showCreditRoles')) {
                    HookRegistry::register('TemplateManager::display', [$this, 'handleTemplateDisplay']);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.credit.name');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.credit.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();

                $form = new CreditSettingsForm($this, $context->getId());
                $form->initData();
                return new JSONMessage(true, $form->fetch($request));
            case 'save':
                $context = $request->getContext();

                $form = new CreditSettingsForm($this, $context->getId());
                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
        }
        return parent::manage($args, $request);
    }

    /**
     * Hook callback: register output filter for article display.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     *
     * @see TemplateManager::display()
     *
     */
    public function handleTemplateDisplay($hookName, $args)
    {
        $templateMgr = & $args[0];
        $template = & $args[1];
        $request = Application::get()->getRequest();

        // Add the localized CRedit role array
        $templateMgr->assign(array(
            'creditRoles' => $this->getCreditRoles(AppLocale::getLocale())
            )
        );

        // Assign our private stylesheet, for front and back ends.
        $templateMgr->addStyleSheet(
            'creditPlugin',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles.css',
            [
                'contexts' => ['frontend']
            ]
        );

        switch ($template) {
            case 'frontend/pages/article.tpl':
                $templateMgr->registerFilter('output', [$this, 'articleDisplayFilter']);
                break;
        }
        return false;
    }

    /**
     * Output filter adds CRediT information to article view.
     *
     * @param string $output
     * @param TemplateManager $templateMgr
     *
     * @return string
     */
    public function articleDisplayFilter($output, $templateMgr)
    {
        $authorIndex = 0;
        $publication = $templateMgr->getTemplateVars('publication');
        $creditRoles = $this->getCreditRoles(AppLocale::getLocale());
        $authors = array_values(iterator_to_array($publication->getData('authors')));

        // Identify the ul.authors list and traverse li/ul/ol elements from there.
        // For any </li> elements in 1st-level depth, append CRediT information before </li>.
        $startMarkup = '<ul class="authors">';
        $startOffset = strpos($output, $startMarkup);
        if ($startOffset === false) return $output;
        $startOffset += strlen($startMarkup);
        $depth = 1; // Depth of potentially nested ul/ol list elements
        return substr($output, 0, $startOffset) . preg_replace_callback(
            '/(<\/li>)|(<[uo]l[^>]*>)|(<\/[uo]l>)/i',
            function($matches) use (&$depth, &$authorIndex, $authors, $creditRoles) {
                switch (true) {
                    case $depth == 1 && $matches[1] !== '': // </li> in first level depth
                        $newOutput = '<ul class="userGroup">';
                        foreach ((array) $authors[$authorIndex++]->getData('creditRoles') as $roleUri) {
                            $newOutput .= '<li class="creditRole">' . htmlspecialchars($creditRoles[$roleUri]) . "</li>\n";
                        }
                        $newOutput .= '</ul>';
                        return $newOutput . $matches[0];
                    case !empty($matches[2]) && $depth >= 1: $depth++; break; // <ul>; do not re-enter once we leave
                    case !empty($matches[3]): $depth--; break; // </ul>
                }
                return $matches[0];
            },
            substr($output, $startOffset)
        );
    }

	/**
	 * Hook callback to handle form display.
	 * Registers output filter for public user profile and author form.
	 *
	 * @param $hookName string
	 * @param $args Form[]
	 *
	 * @return bool
	 * @see Form::display()
	 *
	 */
	function handleAuthorFormDisplay($hookName, $args) {
		$request = PKPApplication::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);
		switch ($hookName) {
			case 'authorform::display':
				$authorForm =& $args[0];
				$author = $authorForm->getAuthor();
                // Build a list of roles for selection in the UI.
                $roleList = $this->getCreditRoles(AppLocale::getLocale());

                $authorCreditRoles = [];
                if ($author) {
                    $authorCreditRoles = $author->getData('creditRoles') ? $author->getData('creditRoles') : [];
                }

                $templateMgr->assign(
                    array(
                        'authorCreditRoles' => $authorCreditRoles,
                        'creditRoles' => $roleList
                        )
                    );

				$templateMgr->registerFilter("output", array($this, 'authorFormFilter'));
				break;
		}
		return false;
	}

    function handleAuthorFormExecute ($hookName, $params): void {
		$form =& $params[0];
		$form->readUserVars(array('creditRoles'));

		$author = $form->getAuthor();
		if ($author) {
		    $author->setData('creditRoles',$form->getData('creditRoles'));
		}	
	}

	/**
	 * Output filter adds CRedit interaction to contributors metadata add/edit form.
	 *
	 * @param $output string
	 * @param $templateMgr TemplateManager
	 * @return string
	 */
	function authorFormFilter($output, $templateMgr) {
		if (preg_match('/<input[^>]+name="submissionId"[^>]*>/', $output, $matches, PREG_OFFSET_CAPTURE)) {
			$match = $matches[0][0];
			$offset = $matches[0][1];
			$newOutput = substr($output, 0, $offset + strlen($match));

            // Maybe use a form element for this?
			$newOutput .= $templateMgr->fetch($this->getTemplateResource('authorFormCRedit.tpl'));
            
    //     $form->addField(new FieldOptions('creditRoles', [
    //         'type' => 'checkbox',
    //         'label' => __('plugins.generic.credit.contributorRoles'),
    //         'description' => __('plugins.generic.credit.contributorRoles.description'),
    //         'options' => $roleList,
    //         'value' => $author?->getData('creditRoles') ?? [],
    //     ]));

            $newOutput .= substr($output, $offset + strlen($match));
			$output = $newOutput;
			$templateMgr->unregisterFilter('output', array($this, 'authorFormFilter'));
		}
		return $output;
	}    

    /**
     * Get the credit roles in an associative URI => Term array.
     * @param $locale The locale for which to fetch the data (en_US if not available)
     */
    public function getCreditRoles($locale): array {
        $roleList = [];
        $doc = new DOMDocument();
        // if (!Locale::isLocaleValid($locale)) $locale = 'en';
        $locale = 'en';
        if (file_exists($filename = dirname(__FILE__) . '/translations/credit-roles-' . $locale . '.xml')) {
            $doc->load($filename);
        } else {
            $doc->load(dirname(__FILE__) . '/jats-schematrons/schematrons/1.0/credit-roles.xml');
        }
        foreach ($doc->getElementsByTagName('credit-roles') as $roles) {
            foreach ($roles->getElementsByTagName('item') as $item) {
                $roleList[$item->getAttribute('uri')] = $item->getAttribute('term');
            }
        }
        return $roleList;
    }
}
