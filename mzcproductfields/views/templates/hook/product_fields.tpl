{**
 * MZC Product Fields — front-office personalization fields block.
 *
 * Field values are read server-side only at the AJAX save step (see the
 * content-confirmation step, plan section 5) via plain input names
 * (mzc_field_{id}) — NOT PrestaShop's native 'textField{id_customization_field}'
 * convention, since we bypass core's own ProductController::textRecord()
 * staging flow entirely (see CartFieldValueWriter's docblock). The
 * form="add-to-cart-or-refresh" attribute is still used so these inputs are
 * included in the native add-to-cart form's serialized data for our own JS
 * to read, exactly like Anvanto's proven trick.
 *}
<div class="mzc-product-fields" data-mzc-product-id="{$mzc_id_product}"
  data-mzc-combination-rules="{$mzc_combination_rules|json_encode|escape:'html':'UTF-8'}"
  data-mzc-save-url="{$mzc_save_customization_url|escape:'html':'UTF-8'}"
  data-mzc-snapshot-save-url="{$mzc_save_snapshot_url|escape:'html':'UTF-8'}"
  data-mzc-i18n="{$mzc_i18n|json_encode|escape:'html':'UTF-8'}"
>
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

  {foreach from=$mzc_fields item=mzc_field}
    <div class="mzc-field mzc-field--{$mzc_field.type}"
      data-mzc-field-id="{$mzc_field.id}"
      data-mzc-preview-x="{$mzc_field.preview_pos_x}"
      data-mzc-preview-y="{$mzc_field.preview_pos_y}"
      data-mzc-preview-font="{$mzc_field.preview_font}"
      data-mzc-preview-font-size="{$mzc_field.preview_font_size}"
      data-mzc-preview-max-width="{$mzc_field.preview_max_width}"
      data-mzc-preview-color="{$mzc_field.preview_color}"
      data-mzc-preview-bg="{$mzc_field.id_background}"
      data-mzc-front-field="{if $mzc_field.is_front_field}1{else}0{/if}"
    >
      <label class="mzc-field__label" for="mzc_field_{$mzc_field.id}">
        {$mzc_field.label|escape:'html':'UTF-8'}
        {if $mzc_field.required}<span class="mzc-field__required">*</span>{/if}
        {if $mzc_field.display_price}<span class="mzc-field__price">+{$mzc_field.display_price}</span>{/if}
      </label>

      {if $mzc_field.type == 'text'}
        <div class="mzc-field__input-wrapper">
          <input type="text" class="mzc-field__input{if $mzc_field.char_counter && $mzc_field.max_length} mzc-field__input--has-counter{/if}" id="mzc_field_{$mzc_field.id}" name="mzc_field_{$mzc_field.id}"
            form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}"
            placeholder="{$mzc_field.placeholder|escape:'html':'UTF-8'}"
            {if $mzc_field.max_length}maxlength="{$mzc_field.max_length}"{/if}
          >
          {if $mzc_field.char_counter && $mzc_field.max_length}
            <div class="mzc-field__counter" data-mzc-counter-for="{$mzc_field.id}" data-mzc-counter-max="{$mzc_field.max_length}">0/{$mzc_field.max_length}</div>
          {/if}
        </div>

      {elseif $mzc_field.type == 'textarea'}
        <div class="mzc-field__input-wrapper">
          <textarea class="mzc-field__input{if $mzc_field.char_counter && $mzc_field.max_length} mzc-field__input--has-counter{/if}" id="mzc_field_{$mzc_field.id}" name="mzc_field_{$mzc_field.id}"
            form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}" rows="3"
            placeholder="{$mzc_field.placeholder|escape:'html':'UTF-8'}"
            {if $mzc_field.max_length}maxlength="{$mzc_field.max_length}"{/if}
          ></textarea>
          {if $mzc_field.char_counter && $mzc_field.max_length}
            <div class="mzc-field__counter" data-mzc-counter-for="{$mzc_field.id}" data-mzc-counter-max="{$mzc_field.max_length}">0/{$mzc_field.max_length}</div>
          {/if}
        </div>

      {elseif $mzc_field.type == 'date'}
        <input type="date" class="mzc-field__input" id="mzc_field_{$mzc_field.id}" name="mzc_field_{$mzc_field.id}"
          form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}"
        >

      {elseif $mzc_field.type == 'select'}
        <select class="mzc-field__input" id="mzc_field_{$mzc_field.id}" name="mzc_field_{$mzc_field.id}"
          form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}"
        >
          <option value="">&mdash;</option>
          {foreach from=$mzc_field.values item=mzc_value}
            <option value="{$mzc_value.id}">{$mzc_value.label|escape:'html':'UTF-8'}{if $mzc_value.display_price} (+{$mzc_value.display_price}){/if}</option>
          {/foreach}
        </select>

      {elseif $mzc_field.type == 'multiselect'}
        <select class="mzc-field__input" id="mzc_field_{$mzc_field.id}" name="mzc_field_{$mzc_field.id}[]"
          form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}" multiple
        >
          {foreach from=$mzc_field.values item=mzc_value}
            <option value="{$mzc_value.id}">{$mzc_value.label|escape:'html':'UTF-8'}{if $mzc_value.display_price} (+{$mzc_value.display_price}){/if}</option>
          {/foreach}
        </select>

      {elseif $mzc_field.type == 'radio'}
        <div class="mzc-field__options">
          {foreach from=$mzc_field.values item=mzc_value}
            <label class="mzc-field__option">
              <input type="radio" name="mzc_field_{$mzc_field.id}" value="{$mzc_value.id}"
                form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}"
              >
              {$mzc_value.label|escape:'html':'UTF-8'}{if $mzc_value.display_price} (+{$mzc_value.display_price}){/if}
            </label>
          {/foreach}
        </div>

      {elseif $mzc_field.type == 'checkbox'}
        <div class="mzc-field__options">
          {foreach from=$mzc_field.values item=mzc_value}
            <label class="mzc-field__option">
              <input type="checkbox" name="mzc_field_{$mzc_field.id}[]" value="{$mzc_value.id}"
                form="add-to-cart-or-refresh" data-mzc-preview-source="{$mzc_field.id}"
              >
              {$mzc_value.label|escape:'html':'UTF-8'}{if $mzc_value.display_price} (+{$mzc_value.display_price}){/if}
            </label>
          {/foreach}
        </div>
      {/if}
    </div>

    {if $mzc_field.backgrounds_after}
      <div class="mzc-bg-previews">
        {foreach from=$mzc_field.backgrounds_after item=mzc_background}
          <div class="mzc-bg-preview" data-mzc-bg-id="{$mzc_background.id_background}" style="display:none;">
            <div class="mzc-bg-preview__clip">
              <img class="mzc-bg-preview__image" src="{$mzc_background.url|escape:'html':'UTF-8'}" alt="">
              <div class="mzc-bg-preview__overlay" aria-hidden="true"></div>
            </div>
          </div>
        {/foreach}
      </div>
    {/if}
  {/foreach}

  <div class="mzc-preview-overlay" aria-hidden="true"></div>
</div>
