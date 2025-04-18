{literal}
<script>
window.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.notifications-container');

  const alertHtml = `
    <article class="alert alert-warning" role="alert" data-alert="warning">
      <ul style="margin-bottom: 0;">
        <li><strong>Heads up:</strong> Some products could not be restored due to limited stock. Please double check your cart items.</li>
        {/literal}
          {foreach from=$out_of_stock_products item=product}
            <li>❌ {$product}</li>
          {/foreach}
        {literal}
      </ul>
    </article>
  `;

  if (container) {
    container.insertAdjacentHTML('afterbegin', alertHtml);
  } else {
    // fallback (optional)
    const fallback = document.createElement('div');
    fallback.className = 'alert alert-warning';
    fallback.innerHTML = alertHtml;
    document.body.prepend(fallback);
  }
});
</script>
{/literal}