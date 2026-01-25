// BuilderZ - Main JavaScript

// Confirm delete actions
function confirmDelete(message = 'Are you sure you want to delete this?') {
    return confirm(message);
}

// Show modal
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

// Hide modal
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// Close modal on click outside
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
    }
});

// Format currency input
function formatCurrency(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    if (value) {
        input.value = parseFloat(value).toFixed(2);
    }
}

// Calculate flat total value
function calculateFlatTotal() {
    const area = parseFloat(document.getElementById('area_sqft')?.value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_sqft')?.value) || 0;
    const totalField = document.getElementById('total_value');

    if (totalField) {
        totalField.value = (area * rate).toFixed(2);
    }
}

// AJAX form submission helper
function submitAjaxForm(formId, successCallback) {
    const form = document.getElementById(formId);
    if (!form) return;

    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (successCallback) {
                    successCallback(data);
                } else {
                    location.reload();
                }
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
}

// Print functionality
function printContent(elementId) {
    const content = document.getElementById(elementId);
    if (content) {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="' + BASE_URL + 'assets/css/style.css">');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
}

// Export table to CSV
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let row of rows) {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        for (let col of cols) {
            csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(csvRow.join(','));
    }

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

// Number formatting
function formatNumber(num, decimals = 2) {
    if (isNaN(num)) return (0).toFixed(decimals);
    return parseFloat(num).toLocaleString('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// Date formatting for input fields
function setTodayDate(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        const today = new Date().toISOString().split('T')[0];
        input.value = today;
    }
}
