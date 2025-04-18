{extends file='page.tpl'}

{block name='page_content'}
<div class="alert alert-warning text-center" style="padding: 40px;">
    <h2>Some items are no longer available</h2>
    <p>Unfortunately, the following products could not be added back to your cart:</p>

    <ul style="list-style-type: none;">
        {foreach from=$out_of_stock item=product}
            <li>{$product}</li>
        {/foreach}
    </ul>

    <p>Please return to the store and update your cart.</p>

    <a href="{$link->getPageLink('index')}" class="btn btn-primary mt-3">Go to Homepage</a>
{/block}