// Toggle the New Processing form between FIFO (type a Jumbo amount, preview,
// then confirm) and LIFO (the Jumbo field auto-fills with the whole
// last-arrived lot and locks - one click to process, nothing to type).
document.addEventListener('DOMContentLoaded', function () {
  var fifoBtn = document.getElementById('fifoBtn');
  var lifoBtn = document.getElementById('lifoBtn');
  var modeInput = document.getElementById('modeInput');
  var jumboInput = document.getElementById('jumboInput');
  var jumboHelp = document.getElementById('jumboHelp');
  var formAction = document.getElementById('formAction');
  var submitBtn = document.getElementById('submitBtn');
  if (!fifoBtn || !lifoBtn || !modeInput || !jumboInput) return;

  var lifo = window.__lifoInfo || null;
  var lastFifoValue = '';

  function setMode(mode) {
    modeInput.value = mode;
    fifoBtn.classList.toggle('active', mode === 'fifo');
    lifoBtn.classList.toggle('active', mode === 'lifo');

    if (mode === 'lifo') {
      if (!jumboInput.readOnly) lastFifoValue = jumboInput.value;
      jumboInput.readOnly = true;
      jumboInput.required = false;
      jumboInput.classList.add('readonly-calc');
      jumboInput.value = lifo ? lifo.jumbo : '';
      jumboHelp.textContent = lifo
        ? 'Auto-filled from the last-arrived lot: Lot ' + lifo.lotNo + ' (' + lifo.mt + ' MT)'
        : 'No active stock available to process right now.';
      formAction.value = 'process';
      submitBtn.textContent = 'Process Last Lot (LIFO)';
      submitBtn.className = 'btn btn-accent';
    } else {
      jumboInput.readOnly = false;
      jumboInput.required = true;
      jumboInput.classList.remove('readonly-calc');
      jumboInput.value = lastFifoValue;
      jumboHelp.textContent = '';
      formAction.value = 'preview';
      submitBtn.textContent = 'Preview FIFO Allocation';
      submitBtn.className = 'btn btn-outline';
    }
  }

  fifoBtn.addEventListener('click', function () { setMode('fifo'); });
  lifoBtn.addEventListener('click', function () { setMode('lifo'); });
});
