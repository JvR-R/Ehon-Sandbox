<div class="nav-w">
    <div class="nav-wrap">
        <div class="menu">
            <nav class="nav-items" role="navigation">
                <a href="/vmi/details" class="navigation-item">Company configuration</a>
                <?php if($accessLevel <= 2){?>
                    <div class="dropdown">
                        <a href="#" class="navigation-item">Management</a>
                        <div class="dropdown-content">
                            <div class="dropdown-item">
                                <a href="#" class="navigation-item">Create</a>
                                <div class="lateral-dropdown">
                                    <a href="/vmi/Manage/Company/" class="navigation-item">Dispatch Console</a>
                                    <a href="/vmi/Manage/Company/new_dist" class="navigation-item">New Distributor</a>
                                    <a href="/vmi/Manage/Company/new_reseller" class="navigation-item">New Reseller</a>
                                    <a href="/vmi/Manage/Company/new_client" class="navigation-item">New Client</a>
                                    <a href="/vmi/Manage/Company/new_customer" class="navigation-item">New Customer</a>
                                    <a href="/vmi/Manage/Edit/new_console" class="navigation-item">New Console</a>
                                    <a href="/vmi/Manage/Company/new_site" class="navigation-item">New Site</a>
                                    <a href="/vmi/Manage/Company/new_tank" class="navigation-item">New Tank</a>
                                    <a href="/vmi/Manage/Company/new_pump" class="navigation-item">New Pump</a>
                                    <a href="/vmi/Manage/Company/new_tag" class="navigation-item">New TAG</a>
                                    <a href="/vmi/Manage/Company/new_vehicle" class="navigation-item">New Vehicle</a>
                                    <a href="/vmi/Manage/Company/new_driver" class="navigation-item">New Driver</a>
                                    <!-- <a href="/vmi/clients/message_edit" class="navigation-item">Messages</a> -->
                                </div>
                            </div>
                            <div class="dropdown-item">
                                <a href="#" class="navigation-item">Edit</a>
                                <div class="lateral-dropdown-edit">
                                    <a href="/vmi/Manage/Edit/edit_site" class="navigation-item">Edit Sites</a>
                                    <a href="/vmi/Manage/Edit/edit_tags" class="navigation-item">Edit Tags</a>
                                    <a href="/vmi/Manage/Edit/edit_vehicle" class="navigation-item">Edit Vehicles</a>
                                    <a href="/vmi/Manage/Edit/edit_drivers" class="navigation-item">Edit Drivers</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } else {?>
                    <div class="dropdown">
                        <a href="#" class="navigation-item">Manage</a>
                        <div class="dropdown-content">
                            <div class="dropdown-item">
                                <a href="/vmi/Manage/Edit/new_console" class="navigation-item">Add Console</a>
                                <a href="/vmi/Manage/Edit/new_site" class="navigation-item">New Site</a>
                                <a href="/vmi/Manage/Edit/edit_site" class="navigation-item">Edit Sites</a>                                    
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <div class="dropdown">
                    <a href="#" class="navigation-item">Users</a>
                    <div class="dropdown-content">
                        <a href="/vmi/details/user" class="navigation-item">New User</a>
                        <a href="/vmi/details/user-management" class="navigation-item">User Management</a>
                    </div>
                </div>
                <a href="/vmi/details/groups" class="navigation-item">Groups</a>
                <a href="/vmi/details/strapping_chart" class="navigation-item">Strapping Chart</a>
            </nav>
        </div>
    </div>
</div>
<div class = "space"></div>
