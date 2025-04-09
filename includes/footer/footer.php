</div><!-- End of container -->
        </div><!-- End of main-content -->
    </div><!-- End of wrapper -->
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/dbms_project/assets/js/script.js"></script>
    
    <script>
    $(document).ready(function() {
        // Toggle sidebar on mobile
        $('.sidebar-toggle').click(function() {
            $('.wrapper').toggleClass('sidebar-collapsed');
        });
        
        // Dropdown toggle
        $('.dropdown-toggle').click(function() {
            $(this).next('.dropdown-menu').toggleClass('show');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').removeClass('show');
            }
        });
    });
    </script>
    
    <style>
        /* Core layout styles */
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .wrapper {
            display: flex;
            height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: #ecf0f1;
            transition: all 0.3s;
            overflow-y: auto;
            flex-shrink: 0;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #34495e;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
        }
        
        .user-role {
            margin: 0;
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            position: relative;
        }
        
        .sidebar-menu li.active {
            background-color: #34495e;
        }
        
        .sidebar-menu li a {
            display: block;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu li a:hover {
            background-color: #34495e;
        }
        
        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main content styles */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* Header styles */
        .top-header {
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #333;
            margin-right: 20px;
            display: none;
        }
        
        .header-date {
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .header-date i {
            margin-right: 5px;
            color: #3498db;
        }
        
        .header-actions {
            margin-left: auto;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-toggle {
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .dropdown-toggle i:first-child {
            margin-right: 5px;
        }
        
        .dropdown-toggle i:last-child {
            margin-left: 5px;
            font-size: 12px;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            min-width: 180px;
            display: none;
            z-index: 1000;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-menu a i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        /* Content container */
        .container {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 1000;
                transform: translateX(0);
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar-collapsed .sidebar {
                transform: translateX(-100%);
            }
        }
    </style>
</body>
</html>