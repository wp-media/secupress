<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );


$this->set_current_section( 'uploads' );
$this->set_section_description( __( 'WordPress allows by default to add a plugin or theme by simply uploading a zip file. This is not secure since the file can contain any custom php code.<br/>By removing this possibility you ensure that plugins could only be added using the FTP or came from the official repository.', 'secupress' ) );
$this->add_section( __( 'Themes & Plugins Uploads', 'secupress' ) );


$plugin = $this->get_current_plugin();

$this->add_field( array(
	/* translators: %s is a file extension */
	'title'             => sprintf( __( 'Disallow %s uploads', 'secupress' ), '<code>.zip</code>' ),
	'label_for'         => $this->get_field_name( 'activate' ),
	'plugin_activation' => true,
	'type'              => 'checkbox',
	'value'             => (int) secupress_is_submodule_active( 'plugins-themes', 'uploads' ),
	'label'             => __( 'Yes, disable uploads for themes and plugins', 'secupress' ),
) );
