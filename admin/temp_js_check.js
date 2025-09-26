<script>
// FIXED Transaction management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('ðŸš€ Transaction Management JavaScript Loaded');
    
    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            transactionCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = this.checked;
                }
            });
            updateBulkApproveButton();
        });
    }
    
    transactionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkApproveButton);
    });
    
    function updateBulkApproveButton() {
        const checkedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
        if (bulkApproveBtn) {
            bulkApproveBtn.disabled = checkedBoxes.length === 0;
        }
        
        // Update select all checkbox state
        const enabledBoxes = document.querySelectorAll('.transaction-checkbox:not([disabled])');
        const checkedEnabledBoxes = document.querySelectorAll('.transaction-checkbox:not([disabled]):checked');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = checkedEnabledBoxes.length > 0 && checkedEnabledBoxes.length < enabledBoxes.length;
            selectAllCheckbox.checked = enabledBoxes.length > 0 && checkedEnabledBoxes.length === enabledBoxes.length;
        }
    }
    
    // Bulk approve
    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.transaction-checkbox:checked');
            if (checkedBoxes.length === 0) return;
            
            if (confirm(`Are you sure you want to approve ${checkedBoxes.length} transaction(s)?`)) {
                const form = document.getElementById('bulkActionForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_approve';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        });
    }
    
    // FIXED: Action Button Event Listeners (instead of onclick attributes)
    setupActionButtons();
});

// FIXED: Setup Action Buttons with Event Listeners
function setupActionButtons() {
    console.log('ðŸ”§ Setting up action button event listeners...');
    
    // View Transaction Buttons
    document.querySelectorAll('[onclick*="viewTransaction"]').forEach(button => {
        const match = button.getAttribute('onclick').match(/viewTransaction\((\d+)\)/);
        if (match) {
            const transactionId = match[1];
            button.removeAttribute('onclick'); // Remove old onclick
            button.addEventListener('click', function() {
                viewTransactionFixed(transactionId);
            });
        }
    });
    
    // Approve Transaction Buttons  
    document.querySelectorAll('[onclick*="approveTransaction"]').forEach(button => {
        const match = button.getAttribute('onclick').match(/approveTransaction\((\d+)\)/);
        if (match) {
            const transactionId = match[1];
            button.removeAttribute('onclick'); // Remove old onclick
            button.addEventListener('click', function() {
                approveTransactionFixed(transactionId, button);
            });
        }
    });
    
    // Reject Transaction Buttons
    document.querySelectorAll('[onclick*="rejectTransaction"]').forEach(button => {
        const match = button.getAttribute('onclick').match(/rejectTransaction\((\d+)\)/);
        if (match) {
            const transactionId = match[1];
            button.removeAttribute('onclick'); // Remove old onclick
            button.addEventListener('click', function() {
                rejectTransactionFixed(transactionId, button);
            });
        }
    });
    
    console.log('âœ… Action button event listeners set up successfully');
}

// FIXED: View transaction details
function viewTransactionFixed(transactionId) {
    console.log('ðŸ” View transaction:', transactionId);
    
    // Show loading state
    document.getElementById('transactionDetails').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading transaction details...</p>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
    modal.show();
    
    fetch('get_transaction_details.php?id=' + transactionId, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('ðŸ“¡ Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('ðŸ“„ Response data received');
        if (data.success) {
            document.getElementById('transactionDetails').innerHTML = data.html;
        } else {
            document.getElementById('transactionDetails').innerHTML = 
                '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error: ' + 
                (data.message || 'Unknown error') + '</div>';
        }
    })
    .catch(error => {
        console.error('âŒ Error:', error);
        document.getElementById('transactionDetails').innerHTML = 
            '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading transaction details: ' + 
            error.message + '</div>';
    });
}

// FIXED: Approve transaction
function approveTransactionFixed(transactionId, button) {
    console.log('âœ… Approve transaction:', transactionId);
    
    try {
        // Get transaction data from the button's data attributes
        const buttonGroup = button.closest('.btn-group');
        const userId = buttonGroup.dataset.userId || '0';
        const amount = buttonGroup.dataset.amount || '0';
        const currency = buttonGroup.dataset.currency || 'HTG';
        const type = buttonGroup.dataset.type || 'unknown';
        const userName = buttonGroup.dataset.userName || 'Unknown User';
        const userEmail = buttonGroup.dataset.userEmail || 'No email';
        
        // Populate modal form fields
        document.getElementById('approvalTransactionId').value = transactionId;
        document.getElementById('approvalAmount').value = amount;
        document.getElementById('approvalCurrency').value = currency;
        document.getElementById('approvalType').value = type;
        document.getElementById('approvalUserId').value = userId;
        
        // Display transaction info
        document.getElementById('approvalTransactionInfo').innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>Transaction Information:</h6>
                    <p class="mb-1"><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p class="mb-1"><strong>User:</strong> ${userName}</p>
                    <p class="mb-1"><strong>Email:</strong> ${userEmail}</p>
                    <p class="mb-1"><strong>Type:</strong> ${type.charAt(0).toUpperCase() + type.slice(1)}</p>
                    <p class="mb-0"><strong>Amount:</strong> ${parseFloat(amount).toLocaleString()} ${currency}</p>
                </div>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
        modal.show();
    } catch (error) {
        console.error('âŒ Error loading transaction data for approval:', error);
        alert('Error loading transaction data for approval. Please try again.');
    }
}

// FIXED: Reject transaction
function rejectTransactionFixed(transactionId, button) {
    console.log('âŒ Reject transaction:', transactionId);
    
    try {
        // Get transaction data from the button's data attributes
        const buttonGroup = button.closest('.btn-group');
        const userId = buttonGroup.dataset.userId || '0';
        const amount = buttonGroup.dataset.amount || '0';
        const currency = buttonGroup.dataset.currency || 'HTG';
        const type = buttonGroup.dataset.type || 'unknown';
        const userName = buttonGroup.dataset.userName || 'Unknown User';
        const userEmail = buttonGroup.dataset.userEmail || 'No email';
        
        // Populate modal form fields
        document.getElementById('rejectionTransactionId').value = transactionId;
        document.getElementById('rejectionAmount').value = amount;
        document.getElementById('rejectionCurrency').value = currency;
        document.getElementById('rejectionType').value = type;
        document.getElementById('rejectionUserId').value = userId;
        
        // Display transaction info
        document.getElementById('rejectionTransactionInfo').innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>Transaction Information:</h6>
                    <p class="mb-1"><strong>Transaction ID:</strong> ${transactionId}</p>
                    <p class="mb-1"><strong>User:</strong> ${userName}</p>
                    <p class="mb-1"><strong>Email:</strong> ${userEmail}</p>
                    <p class="mb-1"><strong>Type:</strong> ${type.charAt(0).toUpperCase() + type.slice(1)}</p>
                    <p class="mb-0"><strong>Amount:</strong> ${parseFloat(amount).toLocaleString()} ${currency}</p>
                </div>
            </div>
        `;
        
        // Clear previous reason
        document.getElementById('rejection_reason').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
        modal.show();
    } catch (error) {
        console.error('âŒ Error loading transaction data for rejection:', error);
        alert('Error loading transaction data for rejection. Please try again.');
    }
}
</script>
