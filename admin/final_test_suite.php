<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Action Buttons Final Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-result { border-left: 4px solid #28a745; background: #f8fff9; }
        .test-error { border-left: 4px solid #dc3545; background: #fff8f8; }
        .test-info { border-left: 4px solid #17a2b8; background: #f7fdff; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1>🔧 Transaction Action Buttons - Final Test Suite</h1>
        <p class="text-muted">Comprehensive testing of all fixes applied</p>

        <?php
        session_start();
        require_once '../includes/config.php';
        require_once '../includes/functions.php';
        
        // Create admin session
        if (!isset($_SESSION['admin_id'])) {
            $_SESSION['admin_id'] = 1;
            $_SESSION['user_id'] = '1';
            $_SESSION['is_admin'] = true;
            $_SESSION['user_role'] = 'admin';
        }
        ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🧪 Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div id="testResults">
                            <div class="text-center py-3">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Running tests...</span>
                                </div>
                                <p class="mt-2">Running comprehensive tests...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h4>🚀 Quick Actions</h4>
                    <div class="btn-group" role="group">
                        <a href="transactions.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>Original Transactions Page
                        </a>
                        <a href="transactions_fixed.php" class="btn btn-success">
                            <i class="fas fa-wrench me-2"></i>Fixed Transactions Page
                        </a>
                        <button class="btn btn-info" onclick="runTests()">
                            <i class="fas fa-redo me-2"></i>Re-run Tests
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">📋 Test Console</h6>
                    </div>
                    <div class="card-body">
                        <div id="console" style="height: 400px; overflow-y: auto; background: #f8f9fa; padding: 10px; font-family: monospace; font-size: 0.85em;">
                            <em>Test console output will appear here...</em>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let testResults = [];
        
        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const console = document.getElementById('console');
            const colors = {
                info: '#17a2b8',
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };
            
            console.innerHTML += `<div style="color: ${colors[type]};">[${timestamp}] ${message}</div>`;
            console.scrollTop = console.scrollHeight;
            
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
        
        function addTestResult(name, status, message, details = null) {
            testResults.push({ name, status, message, details });
            updateTestDisplay();
        }
        
        function updateTestDisplay() {
            const container = document.getElementById('testResults');
            let html = '';
            
            testResults.forEach((test, index) => {
                const iconClass = test.status === 'pass' ? 'fa-check text-success' : 
                                 test.status === 'fail' ? 'fa-times text-danger' : 
                                 'fa-question text-warning';
                const cardClass = test.status === 'pass' ? 'test-result' : 
                                 test.status === 'fail' ? 'test-error' : 'test-info';
                
                html += `
                    <div class="card mb-2 ${cardClass}">
                        <div class="card-body py-2">
                            <div class="d-flex align-items-center">
                                <i class="fas ${iconClass} me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${test.name}</h6>
                                    <p class="mb-0 small text-muted">${test.message}</p>
                                    ${test.details ? `<pre class="small mt-1 mb-0">${test.details}</pre>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            const passed = testResults.filter(t => t.status === 'pass').length;
            const total = testResults.length;
            const percentage = total > 0 ? Math.round((passed / total) * 100) : 0;
            
            html = `
                <div class="alert alert-${percentage >= 80 ? 'success' : percentage >= 60 ? 'warning' : 'danger'} mb-3">
                    <h6>Overall Results: ${passed}/${total} tests passed (${percentage}%)</h6>
                </div>
                ${html}
            `;
            
            container.innerHTML = html;
        }
        
        async function runTests() {
            testResults = [];
            log('🚀 Starting comprehensive test suite...', 'info');
            
            // Test 1: Check if page loads
            try {
                log('📄 Testing main transactions page...', 'info');
                const response = await fetch('transactions.php');
                if (response.ok) {
                    addTestResult('Page Load Test', 'pass', 'Main transactions page loads successfully');
                    log('✅ Page loads successfully', 'success');
                } else {
                    addTestResult('Page Load Test', 'fail', `HTTP ${response.status}: ${response.statusText}`);
                    log(`❌ Page load failed: ${response.status}`, 'error');
                }
            } catch (error) {
                addTestResult('Page Load Test', 'fail', `Error: ${error.message}`);
                log(`❌ Page load error: ${error.message}`, 'error');
            }
            
            // Test 2: Check AJAX endpoint
            try {
                log('🌐 Testing transaction details endpoint...', 'info');
                const response = await fetch('get_transaction_details.php?id=1', {
                    credentials: 'same-origin'
                });
                const data = await response.text();
                
                if (response.ok && data.includes('success')) {
                    addTestResult('AJAX Endpoint Test', 'pass', 'Transaction details endpoint working');
                    log('✅ AJAX endpoint working', 'success');
                } else {
                    addTestResult('AJAX Endpoint Test', 'fail', 'Endpoint not returning expected JSON');
                    log('❌ AJAX endpoint issues', 'error');
                }
            } catch (error) {
                addTestResult('AJAX Endpoint Test', 'fail', `Error: ${error.message}`);
                log(`❌ AJAX endpoint error: ${error.message}`, 'error');
            }
            
            // Test 3: Check Bootstrap Modal
            try {
                log('🪟 Testing Bootstrap Modal functionality...', 'info');
                if (window.bootstrap && window.bootstrap.Modal) {
                    addTestResult('Bootstrap Modal Test', 'pass', 'Bootstrap Modal class available');
                    log('✅ Bootstrap Modal available', 'success');
                } else {
                    addTestResult('Bootstrap Modal Test', 'fail', 'Bootstrap Modal not loaded');
                    log('❌ Bootstrap Modal not available', 'error');
                }
            } catch (error) {
                addTestResult('Bootstrap Modal Test', 'fail', `Error: ${error.message}`);
                log(`❌ Bootstrap Modal error: ${error.message}`, 'error');
            }
            
            // Test 4: Check for JavaScript errors
            try {
                log('📝 Testing JavaScript function availability...', 'info');
                
                // Test if we can create a test modal
                const testModal = document.createElement('div');
                testModal.className = 'modal';
                testModal.id = 'testModal';
                testModal.innerHTML = '<div class="modal-dialog"><div class="modal-content"><div class="modal-body">Test</div></div></div>';
                document.body.appendChild(testModal);
                
                const modal = new bootstrap.Modal(testModal);
                modal.show();
                setTimeout(() => {
                    modal.hide();
                    document.body.removeChild(testModal);
                }, 100);
                
                addTestResult('JavaScript Functions Test', 'pass', 'Modal creation and manipulation working');
                log('✅ JavaScript functions working', 'success');
            } catch (error) {
                addTestResult('JavaScript Functions Test', 'fail', `Error: ${error.message}`);
                log(`❌ JavaScript function error: ${error.message}`, 'error');
            }
            
            // Test 5: Database connectivity
            try {
                log('🗄️ Testing database connectivity...', 'info');
                const response = await fetch('debug_transactions.php');
                const data = await response.text();
                
                if (response.ok && data.includes('Database connection')) {
                    addTestResult('Database Test', 'pass', 'Database connection successful');
                    log('✅ Database connectivity working', 'success');
                } else {
                    addTestResult('Database Test', 'fail', 'Database connection issues');
                    log('❌ Database connectivity issues', 'error');
                }
            } catch (error) {
                addTestResult('Database Test', 'fail', `Error: ${error.message}`);
                log(`❌ Database test error: ${error.message}`, 'error');
            }
            
            log('🏁 Test suite completed!', 'success');
            
            // Generate recommendation
            const passed = testResults.filter(t => t.status === 'pass').length;
            const total = testResults.length;
            const percentage = Math.round((passed / total) * 100);
            
            if (percentage >= 80) {
                log('🎉 EXCELLENT! Action buttons should be working now.', 'success');
            } else if (percentage >= 60) {
                log('⚠️ GOOD progress, but some issues remain.', 'warning');
            } else {
                log('🚨 ISSUES detected - action buttons may still have problems.', 'error');
            }
        }
        
        // Auto-run tests on page load
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 Page loaded, initializing test suite...', 'info');
            setTimeout(runTests, 1000);
        });
    </script>
</body>
</html>