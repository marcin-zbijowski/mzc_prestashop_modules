{*
 * MZC Landing Newsletter
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-css3"></i> {$heading_docs|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <p>{$desc_docs|escape:'html':'UTF-8'}</p>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>{$th_class|escape:'html':'UTF-8'}</th>
                        <th>{$th_element|escape:'html':'UTF-8'}</th>
                        <th>{$th_description|escape:'html':'UTF-8'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $css_classes as $row}
                        <tr>
                            <td><code>{$row.selector|escape:'html':'UTF-8'}</code></td>
                            <td>{$row.element|escape:'html':'UTF-8'}</td>
                            <td>{$row.description|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <br>
        <p><strong>{$label_example|escape:'html':'UTF-8'}</strong></p>
        <pre class="mzc-css-example">{$css_example|escape:'html':'UTF-8'}</pre>
    </div>
</div>
