{extends file='page.tpl'}

{block name='page_content'}
<div class="alert alert-danger text-center" style="padding: 40px;">
    <h2>Payment Failed</h2>
    <p>Unfortunately, your payment could not be completed or was declined.</p>

    <p>If you believe this is an error, please contact support or try placing the order again.</p>
    <p>You can try again by clicking the button below.</p>

    <form method="post" action="{$link->getModuleLink('leanx', 'failure')}">
        <input type="hidden" name="order_id" value="{$order_id}" />
        <button type="submit" name="retry_checkout" class="btn btn-primary mt-3">
            Retry Checkout
        </button>
    </form>
</div>
{/block}