<!-- Summary Details Modal -->
<div id="summaryModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="summaryModalTitle">Details</h2>
            <button class="modal-close" onclick="closeSummaryModal()">&times;</button>
        </div>
        <div id="summaryModalBody" class="modal-body" style="position: relative; min-height: 200px;">
            <!-- Loading Spinner -->
            <div id="summaryModalLoader" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: var(--panel-bg); z-index: 10;">
                <div class="spinner"></div>
            </div>
            <!-- Dynamic Content -->
            <div id="summaryModalContent"></div>
        </div>
    </div>
</div>

<script>
function openSummaryModal(type, id = null, name = null) {
    const modal = document.getElementById('summaryModal');
    const loader = document.getElementById('summaryModalLoader');
    const content = document.getElementById('summaryModalContent');
    const title = document.getElementById('summaryModalTitle');
    
    // Reset modal
    content.innerHTML = '';
    title.innerText = 'Loading...';
    loader.style.display = 'flex';
    modal.classList.add('active');

    // Build URL
    let url = `<?= h(app_url('/api/summary_details.php')) ?>?type=${type}`;
    if (type === 'municipality') url += `&name=${encodeURIComponent(name)}`;
    if (type === 'barangay') url += `&id=${id}`;

    // Fetch details
    fetch(url)
        .then(res => res.json())
        .then(data => {
            loader.style.display = 'none';
            if (data.error) {
                content.innerHTML = `<div class="alert error">${data.error}</div>`;
                title.innerText = 'Error';
            } else {
                title.innerText = data.title;
                content.innerHTML = data.html;
            }
        })
        .catch(err => {
            loader.style.display = 'none';
            content.innerHTML = `<div class="alert error">Failed to load details.</div>`;
            title.innerText = 'Error';
            console.error(err);
        });
}

function closeSummaryModal() {
    document.getElementById('summaryModal').classList.remove('active');
}

// Close modal on click outside
document.getElementById('summaryModal').addEventListener('click', function(e) {
    if (e.target === this) closeSummaryModal();
});
</script>
