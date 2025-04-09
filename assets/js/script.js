/**
 * Main JavaScript file for Retail POS System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if Bootstrap is used
    if(typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Flash messages auto-hide
    const flashMessages = document.querySelectorAll('.alert-dismissible');
    if(flashMessages.length > 0) {
        flashMessages.forEach(function(flash) {
            setTimeout(function() {
                flash.classList.add('fade-out');
                setTimeout(function() {
                    flash.remove();
                }, 500);
            }, 5000);
        });
    }
    
    // DateTime fields initialization
    const dateTimeFields = document.querySelectorAll('.date-time-picker');
    if(dateTimeFields.length > 0) {
        dateTimeFields.forEach(function(field) {
            // If using a date library like flatpickr
            if(typeof flatpickr !== 'undefined') {
                flatpickr(field, {
                    enableTime: true,
                    dateFormat: "Y-m-d H:i"
                });
            }
        });
    }
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.delete-btn, .btn-delete, [data-action="delete"]');
    if(deleteButtons.length > 0) {
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                if(!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    }
    
    // Table row highlighting on hover
    const dataTables = document.querySelectorAll('.data-table');
    if(dataTables.length > 0) {
        dataTables.forEach(function(table) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                row.addEventListener('mouseenter', function() {
                    this.classList.add('highlight');
                });
                
                row.addEventListener('mouseleave', function() {
                    this.classList.remove('highlight');
                });
            });
        });
    }
    
    // Form validation 
    const forms = document.querySelectorAll('.needs-validation');
    if(forms.length > 0) {
        Array.from(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }
    
    // Print functionality
    const printButtons = document.querySelectorAll('.print-btn');
    if(printButtons.length > 0) {
        printButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                window.print();
            });
        });
    }
    
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    if(menuToggle) {
        const sidebar = document.querySelector('.sidebar');
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    console.log('POS System JavaScript initialized');
});