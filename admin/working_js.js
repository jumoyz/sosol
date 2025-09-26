// FIXED JavaScript for Action Buttons - WORKING VERSION
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Fixed Transaction Management JavaScript Loaded');
    
    // View Transaction Details
    document.querySelectorAll('.btn-view').forEach(function(button) {
        button.addEventListener('click', function() {
            const transactionId = this.dataset.transactionId;
            console.log('🔍 View transaction clicked:', transactionId);
            
            // Show modal with loading state
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
            
            // Fetch transaction details
            fetch('get_transaction_details.php?id=' + transactionId, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('📡 Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('📄 Response data:', data);
                if (data.success) {
                    document.getElementById('transactionDetails').innerHTML = data.html;
                } else {
                    document.getElementById('transactionDetails').innerHTML = 
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error: ' + 
                        (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('❌ Error:', error);
                document.getElementById('transactionDetails').innerHTML = 
                    '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading transaction details: ' + 
                    error.message + '</div>';
            });
        });
    });
    
    // Approve Transaction
    document.querySelectorAll('.btn-approve').forEach(function(button) {
        button.addEventListener('click', function() {
            const data = {
                transactionId: this.dataset.transactionId,
                userId: this.dataset.userId,
                amount: this.dataset.amount,
                currency: this.dataset.currency,
                type: this.dataset.type,
                userName: this.dataset.userName,
                userEmail: this.dataset.userEmail
            };
            
            console.log('✅ Approve transaction clicked:', data);
            
            // Populate approval modal
            document.getElementById('approvalTransactionId').value = data.transactionId;
            document.getElementById('approvalAmount').value = data.amount;
            document.getElementById('approvalCurrency').value = data.currency;
            document.getElementById('approvalType').value = data.type;
            document.getElementById('approvalUserId').value = data.userId;
            
            document.getElementById('approvalTransactionInfo').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h6>Transaction Information:</h6>
                        <p class="mb-1"><strong>Transaction ID:</strong> ${data.transactionId}</p>
                        <p class="mb-1"><strong>User:</strong> ${data.userName}</p>
                        <p class="mb-1"><strong>Email:</strong> ${data.userEmail}</p>
                        <p class="mb-1"><strong>Type:</strong> ${data.type.charAt(0).toUpperCase() + data.type.slice(1)}</p>
                        <p class="mb-0"><strong>Amount:</strong> ${parseFloat(data.amount).toLocaleString()} ${data.currency}</p>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
            modal.show();
        });
    });
    
    // Reject Transaction
    document.querySelectorAll('.btn-reject').forEach(function(button) {
        button.addEventListener('click', function() {
            const data = {
                transactionId: this.dataset.transactionId,
                userId: this.dataset.userId,
                amount: this.dataset.amount,
                currency: this.dataset.currency,
                type: this.dataset.type,
                userName: this.dataset.userName,
                userEmail: this.dataset.userEmail
            };
            
            console.log('❌ Reject transaction clicked:', data);
            
            // Populate rejection modal
            document.getElementById('rejectionTransactionId').value = data.transactionId;
            document.getElementById('rejectionAmount').value = data.amount;
            document.getElementById('rejectionCurrency').value = data.currency;
            document.getElementById('rejectionType').value = data.type;
            document.getElementById('rejectionUserId').value = data.userId;
            
            document.getElementById('rejectionTransactionInfo').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <h6>Transaction Information:</h6>
                        <p class="mb-1"><strong>Transaction ID:</strong> ${data.transactionId}</p>
                        <p class="mb-1"><strong>User:</strong> ${data.userName}</p>
                        <p class="mb-1"><strong>Email:</strong> ${data.userEmail}</p>
                        <p class="mb-1"><strong>Type:</strong> ${data.type.charAt(0).toUpperCase() + data.type.slice(1)}</p>
                        <p class="mb-0"><strong>Amount:</strong> ${parseFloat(data.amount).toLocaleString()} ${data.currency}</p>
                    </div>
                </div>
            `;
            
            // Clear previous reason
            document.getElementById('rejection_reason').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
            modal.show();
        });
    });
    
    console.log('✅ All event listeners attached successfully');
});