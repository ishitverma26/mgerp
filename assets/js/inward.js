// Live "per Jumbo weight" preview on the Raw Material Inward form.
// This is a UI convenience only - the server recalculates the same
// value again in PHP before saving, so a tampered client value can
// never be trusted or stored as-is.
document.addEventListener('DOMContentLoaded', function () {
  var gross = document.getElementById('gross_weight');
  var jumbo = document.getElementById('jumbo_qty');
  var perJumboOut = document.getElementById('per_jumbo_preview');
  if (!gross || !jumbo || !perJumboOut) return;

  function recalc() {
    var g = parseFloat(gross.value);
    var j = parseInt(jumbo.value, 10);
    if (g > 0 && j > 0) {
      perJumboOut.value = (g / j).toFixed(6) + ' MT / Jumbo';
    } else {
      perJumboOut.value = '';
    }
  }
  gross.addEventListener('input', recalc);
  jumbo.addEventListener('input', recalc);
});
