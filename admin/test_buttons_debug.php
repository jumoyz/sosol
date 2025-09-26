<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Buttons Debug Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>🔍 Action Buttons Debug Test</h2>
        
        <?php
        session_start();
        
        // Create admin session
        if (!isset($_SESSION['admin_id'])) {
            $_SESSION['admin_id'] = 1;
            $_SESSION['user_id'] = '1';
            $_SESSION['is_admin'] = true;
            $_SESSION['user_role'] = 'admin';
        }
        
        echo "<div class='alert alert-info'>Admin session: " . ($_SESSION['admin_id'] ?? 'Not set') . "</div>";
        ?>
        
        <div class="row">
            <div class="col-md-6">
                <h4>Test 1: Basic Button Clicks</h4>
                <p>These buttons should trigger JavaScript alerts:</p>
                
                <div class="btn-group btn-group-sm mb-3" 
                     data-transaction-id="1"
                     data-user-id="1"
                     data-amount="100.50"
                     data-currency="HTG"
                     data-type="deposit"
                     data-user-name="Test User"
                     data-user-email="test@example.com">
                    <button type="button" class="btn btn-outline-primary" 
                            onclick="testViewTransaction(1)"
                            title="View Details">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button type="button" class="btn btn-outline-success" 
                            onclick="testApproveTransaction(1)"
                            title="Approve Transaction">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button type="button" class="btn btn-outline-danger" 
                            onclick="testRejectTransaction(1)"
                            title="Reject Transaction">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </div>
                
                <h4>Test 2: AJAX Endpoint Test</h4>
                <button class="btn btn-primary" onclick="testAjaxEndpoint()">
                    Test get_transaction_details.php
                </button>
                
                <div id="ajaxResult" class="mt-3"></div>
            </div>
            
            <div class="col-md-6">
                <h4>Test 3: Console Output</h4>
                <p>Open browser console (F12) to see detailed logs</p>
                
                <div id="testOutput" class="border p-3" style="height: 300px; overflow-y: auto; background: #f8f9fa;">
                    <em>Test results will appear here...</em>
                </div>
            </div>
        </div>
        
        <h4>Test 4: Actual Transaction Page Buttons</h4>
        <p><a href="transactions.php" class="btn btn-primary">Go to Transactions Page</a></p>
    </div>

    <!-- Bootstrap Modal for Testing -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Modal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="testModalBody">
                    Modal content will appear here
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function log(message) {
            console.log(message);
            const output = document.getElementById('testOutput');
            output.innerHTML += '<div class="mb-1">[' + new Date().toLocaleTimeString() + '] ' + message + '</div>';
            output.scrollTop = output.scrollHeight;
        }
        
        function testViewTransaction(id) {
            log('🔍 testViewTransaction called with ID: ' + id);
            
            const buttonGroup = event.target.closest('.btn-group');
            if (!buttonGroup) {
                log('❌ ERROR: Could not find button group');
                alert('ERROR: Could not find button group');
                return;
            }
            
            log('✅ Button group found with data attributes');
            log('📋 Data attributes: ' + JSON.stringify({
                transactionId: buttonGroup.dataset.transactionId,
                userId: buttonGroup.dataset.userId,
                amount: buttonGroup.dataset.amount,
                currency: buttonGroup.dataset.currency,
                type: buttonGroup.dataset.type,
                userName: buttonGroup.dataset.userName,
                userEmail: buttonGroup.dataset.userEmail
            }, null, 2));
            
            // Test modal opening
            document.getElementById('testModalBody').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Testing modal...</div>';
            new bootstrap.Modal(document.getElementById('testModal')).show();
            
            alert('✅ View function working! Check console for details.');
        }
        
        function testApproveTransaction(id) {
            log('✅ testApproveTransaction called with ID: ' + id);
            
            const buttonGroup = event.target.closest('.btn-group');
            if (buttonGroup) {
                const data = {
                    id: id,
                    userId: buttonGroup.dataset.userId,
                    amount: buttonGroup.dataset.amount,
                    currency: buttonGroup.dataset.currency,
                    type: buttonGroup.dataset.type,
                    userName: buttonGroup.dataset.userName,
                    userEmail: buttonGroup.dataset.userEmail
                };
                log('📋 Extracted data: ' + JSON.stringify(data, null, 2));
                alert('✅ Approve function working! Data: ' + JSON.stringify(data));
            } else {
                log('❌ ERROR: Could not find button group for approve');
                alert('❌ ERROR: Could not find button group');
            }
        }
        
        function testRejectTransaction(id) {
            log('❌ testRejectTransaction called with ID: ' + id);
            
            const buttonGroup = event.target.closest('.btn-group');
            if (buttonGroup) {
                const data = {
                    id: id,
                    userId: buttonGroup.dataset.userId,
                    amount: buttonGroup.dataset.amount,
                    currency: buttonGroup.dataset.currency,
                    type: buttonGroup.dataset.type,
                    userName: buttonGroup.dataset.userName,
                    userEmail: buttonGroup.dataset.userEmail
                };
                log('📋 Extracted data: ' + JSON.stringify(data, null, 2));
                alert('✅ Reject function working! Data: ' + JSON.stringify(data));
            } else {
                log('❌ ERROR: Could not find button group for reject');
                alert('❌ ERROR: Could not find button group');
            }
        }
        
        function testAjaxEndpoint() {
            log('🌐 Testing AJAX endpoint...');
            document.getElementById('ajaxResult').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Testing...</div>';
            
            fetch('get_transaction_details.php?id=1', {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => {
                log('📡 Response status: ' + response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text(); // Get as text first to see what we're getting
            })
            .then(data => {
                log('📄 Response received (length: ' + data.length + ')');
                log('📄 Response preview: ' + data.substring(0, 200) + '...');
                
                document.getElementById('ajaxResult').innerHTML = 
                    '<div class="alert alert-success">✅ AJAX Success!</div>' +
                    '<pre style="max-height: 200px; overflow-y: auto;">' + data + '</pre>';
                
                // Try to parse as JSON
                try {
                    const jsonData = JSON.parse(data);
                    log('✅ Valid JSON response received');
                } catch (e) {
                    log('⚠️ Response is not valid JSON: ' + e.message);
                }
            })
            .catch(error => {
                log('❌ AJAX Error: ' + error.message);
                document.getElementById('ajaxResult').innerHTML = 
                    '<div class="alert alert-danger">❌ AJAX Error: ' + error.message + '</div>';
            });
        }
        
        // Test on page load
        document.addEventListener('DOMContentLoaded', function() {
            log('✅ Page loaded and JavaScript initialized');
            log('🔧 Bootstrap version: ' + (window.bootstrap ? 'Loaded' : 'NOT LOADED'));
            log('📱 Current URL: ' + window.location.href);
        });
    </script>
</body>
</html>