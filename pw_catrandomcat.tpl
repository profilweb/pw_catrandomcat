{**
 * PW Cat Random Cat: module for PrestaShop.
 *
 * @author    profilweb. <manu@profil-web.fr>
 * @copyright 2026 profil Web.
 * @link      https://github.com/profilweb/pw_homecategories The module's homepage
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 *}

{if !empty($random_categories)}
    <div class="pw-random-categories">
        <h3 class="title">Nos catégories en avant</h3>
        <div class="row">
            {foreach $random_categories as $category}
                <div class="col-md-4 category-item text-center mb-4">
                    <a href="{$category.link}" title="{$category.name}" class="d-block">
                        {if $category.image}
                            <img src="{$category.image}" alt="{$category.name}" class="img-fluid mb-2">
                        {/if}
                        <h4 class="category-name">{$category.name}</h4>
                    </a>
                </div>
            {/foreach}
        </div>
    </div>
{/if}
