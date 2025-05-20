/**
 * Admin Dashboard JavaScript
 * This script handles the admin-specific functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Admin dashboard charts
    const userStatsCanvas = document.getElementById('userStatsChart');
    const revenueStatsCanvas = document.getElementById('revenueStatsChart');
    const messageStatsCanvas = document.getElementById('messageStatsChart');
    
    if (userStatsCanvas) {
        const ctx = userStatsCanvas.getContext('2d');
        
        // Example user stats chart
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: userStatsLabels || [],
                datasets: [{
                    label: 'New Users',
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    data: userStatsData || [],
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    if (revenueStatsCanvas) {
        const ctx = revenueStatsCanvas.getContext('2d');
        
        // Example revenue stats chart
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: revenueStatsLabels || [],
                datasets: [{
                    label: 'Revenue',
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 1,
                    data: revenueStatsData || []
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    if (messageStatsCanvas) {
        const ctx = messageStatsCanvas.getContext('2d');
        
        // Example message stats chart
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: messageStatsLabels || [],
                datasets: [{
                    label: 'Messages',
                    backgroundColor: 'rgba(155, 89, 182, 0.2)',
                    borderColor: 'rgba(155, 89, 182, 1)',
                    data: messageStatsData || [],
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    // Bulk actions in admin tables
    const bulkActionForm = document.querySelector('.bulk-action-form');
    const selectAllCheckbox = document.querySelector('.select-all-checkbox');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }
    
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            const action = document.querySelector('.bulk-action-select').value;
            const checkedItems = document.querySelectorAll('.item-checkbox:checked');
            
            if (!action || checkedItems.length === 0) {
                e.preventDefault();
                alert('Please select an action and at least one item.');
            } else if (action === 'delete') {
                if (!confirm('Are you sure you want to delete the selected items? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });
    }
    
    // Payment proof modals
    const proofLinks = document.querySelectorAll('.view-proof');
    
    if (proofLinks.length > 0) {
        proofLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const modalId = this.getAttribute('data-target');
                const modal = document.querySelector(modalId);
                
                if (modal) {
                    modal.style.display = 'block';
                }
            });
        });
    }
    
    // Filter forms
    const filterForms = document.querySelectorAll('.filter-form');
    
    if (filterForms.length > 0) {
        filterForms.forEach(form => {
            const resetButton = form.querySelector('.reset-filters');
            
            if (resetButton) {
                resetButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Reset all form fields
                    const inputs = form.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            input.checked = false;
                        } else {
                            input.value = '';
                        }
                    });
                    
                    // Submit the form
                    form.submit();
                });
            }
        });
    }
    
    // Date range pickers
    const dateRangePickers = document.querySelectorAll('.date-range-picker');
    
    if (dateRangePickers.length > 0 && typeof flatpickr !== 'undefined') {
        dateRangePickers.forEach(picker => {
            flatpickr(picker, {
                mode: 'range',
                dateFormat: 'Y-m-d'
            });
        });
    }
});