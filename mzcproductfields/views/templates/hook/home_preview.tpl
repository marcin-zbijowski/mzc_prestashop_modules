{**
 * MZC Product Fields — home-page showcase.
 *
 * A teaser, not a second product page: one free-text field per showcase
 * product, the real product photo, and the same live preview the product page
 * uses. No pricing rows, no combinations, no confirmation step, no add-to-cart.
 *
 * Each showcase is a full `.mzc-product-fields` container so front-product-fields.js
 * treats it exactly like the product-page block. The photo carries
 * `data-mzc-preview-image`, which that script prefers over its theme-specific
 * IMAGE_SELECTORS list — on the home page there is no product carousel to find.
 *
 * Only the first showcase is visible; the theme reveals the others when a
 * pattern thumbnail is clicked. Inputs deliberately carry NO `form` attribute
 * (the product page's add-to-cart form does not exist here).
 *}
<div class="mzc-home-preview" data-mzc-home-preview>
  {if $mzc_fonts}
    <style>
      {foreach from=$mzc_fonts item=mzc_font}
      @font-face {
        font-family: "{$mzc_font.font_family|escape:'html':'UTF-8'}";
        src: url("{$mzc_font.url|escape:'html':'UTF-8'}");
        font-display: swap;
      }
      {/foreach}
    </style>
  {/if}

  {foreach from=$mzc_showcases item=mzc_showcase name=showcases}
    <div class="mzc-home-preview__item"
      data-mzc-showcase="{$mzc_showcase.id_product}"
      data-mzc-showcase-url="{$mzc_showcase.url|escape:'html':'UTF-8'}"
      data-mzc-showcase-field="{$mzc_showcase.field.id}"
      {if !$smarty.foreach.showcases.first}hidden{/if}
    >
      <div class="mzc-product-fields mzc-product-fields--showcase"
        data-mzc-product-id="{$mzc_showcase.id_product}"
        data-mzc-combination-rules="{$mzc_showcase.combination_rules|json_encode|escape:'html':'UTF-8'}"
      >
        <div class="mzc-home-preview__media">
          <img class="mzc-home-preview__image" data-mzc-preview-image
            src="{$mzc_showcase.image|escape:'html':'UTF-8'}"
            alt="{$mzc_showcase.name|escape:'html':'UTF-8'}" loading="lazy">
        </div>

        <div class="mzc-field mzc-field--{$mzc_showcase.field.type}"
          data-mzc-field-id="{$mzc_showcase.field.id}"
          data-mzc-preview-x="{$mzc_showcase.field.preview_pos_x}"
          data-mzc-preview-y="{$mzc_showcase.field.preview_pos_y}"
          data-mzc-preview-font="{$mzc_showcase.field.preview_font}"
          data-mzc-preview-font-size="{$mzc_showcase.field.preview_font_size}"
          data-mzc-preview-max-width="{$mzc_showcase.field.preview_max_width}"
          data-mzc-preview-color="{$mzc_showcase.field.preview_color}"
          data-mzc-preview-bg=""
          data-mzc-front-field="1"
        >
          <label class="mzc-field__label" for="mzc_showcase_field_{$mzc_showcase.id_product}">
            {$mzc_showcase.field.label|escape:'html':'UTF-8'}
          </label>

          <div class="mzc-field__input-wrapper">
            <input type="text"
              class="mzc-field__input{if $mzc_showcase.field.char_counter && $mzc_showcase.field.max_length} mzc-field__input--has-counter{/if}"
              id="mzc_showcase_field_{$mzc_showcase.id_product}"
              data-mzc-preview-source="{$mzc_showcase.field.id}"
              placeholder="{$mzc_showcase.field.placeholder|escape:'html':'UTF-8'}"
              {if $mzc_showcase.field.max_length}maxlength="{$mzc_showcase.field.max_length}"{/if}
            >
            {if $mzc_showcase.field.char_counter && $mzc_showcase.field.max_length}
              <div class="mzc-field__counter" data-mzc-counter-for="{$mzc_showcase.field.id}" data-mzc-counter-max="{$mzc_showcase.field.max_length}">0/{$mzc_showcase.field.max_length}</div>
            {/if}
          </div>
        </div>

        <div class="mzc-preview-overlay" aria-hidden="true"></div>
      </div>

      <div class="mzc-home-preview__meta">
        <span class="mzc-home-preview__name">{$mzc_showcase.name}</span>
        <span class="mzc-home-preview__price">{$mzc_showcase.price}</span>
      </div>
    </div>
  {/foreach}

  {if $mzc_showcases|count > 1}
    <div class="mzc-home-preview__patterns" role="group">
      {foreach from=$mzc_showcases item=mzc_showcase name=thumbs}
        <button type="button"
          class="mzc-home-preview__pattern{if $smarty.foreach.thumbs.first} is-active{/if}"
          data-mzc-showcase-target="{$mzc_showcase.id_product}"
          aria-pressed="{if $smarty.foreach.thumbs.first}true{else}false{/if}"
          title="{$mzc_showcase.name|escape:'html':'UTF-8'}"
        >
          <img src="{$mzc_showcase.thumb|escape:'html':'UTF-8'}" alt="{$mzc_showcase.name|escape:'html':'UTF-8'}" loading="lazy">
        </button>
      {/foreach}
    </div>
  {/if}
</div>
