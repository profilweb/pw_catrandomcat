<?php
/**
 * PW HomeCatCustom
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @author    Profil Web
 * @copyright Copyright 2026 ©profilweb All right reserved
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @package   pw_catrandomcat
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Pw_CatRandomCat extends Module implements WidgetInterface
{
    private $templateFile;

    public function __construct()
    {
        $this->name = 'pw_catrandomcat';
        $this->author = 'Profil Web';
        $this->version = '1.0.0';
        $this->need_instance = 0;

        $this->ps_versions_compliancy = [
            'min' => '8.0',
            'max' => _PS_VERSION_,
        ];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('PW Cat Random Cat');
        $this->description = $this->l('Display shuffle categories on category page.');

        $this->templateFile = 'module:pw_catrandomcat/pw_catrandomcat.tpl';
    }

    public function install()
    {
        $this->_clearCache('*');
        Configuration::updateValue('PW_CAT_RANDOM_CAT_EXCL', '');

        return parent::install()
            && $this->registerHook('displayFooterCategory');
    }

    public function uninstall()
    {
        $this->_clearCache('*');
        Configuration::deleteByName('PW_CAT_RANDOM_CAT_EXCL');

        return parent::uninstall();
    }

    public function _clearCache($template, $cache_id = null, $compile_id = null)
    {
        parent::_clearCache($this->templateFile);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitPWCatRandomCat')) {
            $excl_cat = Tools::getValue('PW_CAT_RANDOM_CAT_EXCL');
            Configuration::updateValue('PW_CAT_RANDOM_CAT_EXCL', $excl_cat);

            $this->_clearCache('*');
            $output = $this->displayConfirmation($this->l('The settings have been updated.'));
        }

        return $output . $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Categories for exclusion (comma-separated IDs)'),
                        'name' => 'PW_CAT_RANDOM_CAT_EXCL',
                        'desc' => $this->l('Example: 3,5,8 (exclude categories with IDs 3, 5 and 8)'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPWCatRandomCat';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        return [
            'PW_CAT_RANDOM_CAT_EXCL' => Tools::getValue('PW_CAT_RANDOM_CAT_EXCL', Configuration::get('PW_CAT_RANDOM_CAT_EXCL')),
        ];
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId('pw_catrandomcat'))) {
            $variables = $this->getWidgetVariables($hookName, $configuration);

            if (empty($variables)) {
                return false;
            }

            $this->smarty->assign($variables);
        }

        return $this->fetch($this->templateFile, $this->getCacheId('pw_catrandomcat'));
    }

    private function getRandomChildCategories($limit = 3, $excludedIds = [])
    {
        $idLang = (int)$this->context->language->id;
        $idParent = 2; // ID de la catégorie "Accueil"

        // Construire la requête SQL avec exclusions
        $sql = new DbQuery();
        $sql->select('c.`id_category`, cl.`name`, cl.`link_rewrite`');
        $sql->from('category', 'c');
        $sql->innerJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category`');
        $sql->where('c.`id_parent` = ' . (int)$idParent);
        $sql->where('c.`active` = 1');
        $sql->where('cl.`id_lang` = ' . (int)$idLang);

        // Exclure les IDs configurés + catégorie actuelle + parent
        if (!empty($excludedIds)) {
            $sql->where('c.`id_category` NOT IN (' . implode(',', $excludedIds) . ')');
        }

        $sql->orderBy('RAND()');
        $sql->limit($limit);

        $categories = Db::getInstance()->executeS($sql);

        // Ajouter les liens et images
        foreach ($categories as &$category) {
            $cat = new Category($category['id_category'], $idLang);
            $category['link'] = $this->context->link->getCategoryLink($cat);
            $category['image'] = $this->getCategoryImageLink($category['id_category']);
        }

        return $categories;
    }


    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        // Récupérer l'ID de la catégorie actuelle
        $currentCategory = $this->context->controller->getCategory();
        $currentCategoryId = (isset($currentCategory) && $currentCategory) ? (int)$currentCategory->id : 0;
        $currentParentId = (isset($currentCategory) && $currentCategory) ? (int)$currentCategory->id_parent : 0;

        // Récupérer les IDs à exclure depuis la configuration
        $excludedIds = Configuration::get('PW_CAT_RANDOM_CAT_EXCL');
        $excludedIdsArray = array_map('intval', explode(',', $excludedIds));

        // Ajouter la catégorie actuelle et son parent aux exclusions
        if ($currentCategoryId) {
            $excludedIdsArray[] = $currentCategoryId;
        }
        if ($currentParentId) {
            $excludedIdsArray[] = $currentParentId;
        }

        // Supprimer les doublons
        $excludedIdsArray = array_unique($excludedIdsArray);

        // Récupérer les catégories aléatoires (en excluant celles configurées + actuelle + parent)
        $randomCategories = $this->getRandomChildCategories(3, $excludedIdsArray);

        return [
            'random_categories' => $randomCategories,
        ];
    }

    /**
     * Récupère l'URL de l'image de la catégorie
     */
    private function getCategoryImageLink($idCategory)
    {

        $context = Context::getContext();
        $category = new Category($idCategory, $context->language->id);

        $imagePath = _PS_CAT_IMG_DIR_ . $category->id . '.jpg';
        if (!file_exists($imagePath)) {
            return false;
        }

        if (!empty($category->id_image)) {
            return $context->link->getCatImageLink(
                $category->link_rewrite,
                $category->id,
                'category_default'
            );
        }

        return false;
    }

    protected function getCacheId($name = null)
    {
        $cacheId = parent::getCacheId($name);
        if (!empty($this->context->customer->id)) {
            $cacheId .= '|' . $this->context->customer->id;
        }

        return $cacheId;
    }
}
