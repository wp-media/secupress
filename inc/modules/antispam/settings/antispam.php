<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );


$this->set_current_section( 'antispam' );
$this->add_section( __( 'Antispam Rules', 'secupress' ) );

$field_name      = $this->get_field_name( 'antispam' );
$main_field_name = $field_name . '_fightspam';
$this->add_field(
	__( 'Antispam', 'secupress' ),
	array(
		'name'        => $field_name,
		'description'  => __( 'If you do not activate this antispam or remove the comment feature, please, activate another antispam plugin for your security!', 'secupress' ),
	),
	array(
		array(
			'type'         => 'radioboxes',
			'name'         => $field_name,
			'options'      => array(
									'fightspam' => __( 'I <strong>need</strong> this to help my website fighting comment spam', 'secupress' ),
									'remove-comment-feature' => __( 'I <strong>do not need</strong> comments on my website, remove all the comment features.', 'secupress' ),
								),
			'label_for'    => $field_name,
			'label_screen' => __( 'Which antispam do you need', 'secupress' ),
		),
	)
);

$field_name      = $this->get_field_name( 'trust-old-commenters' );
$this->add_field(
	__( 'Trust Old Commenters', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( '', 'secupress' ),
	),
	array(
		'depends'     => $main_field_name,
		array(
			'type'         => 'checkbox',
			'name'         => $field_name,
			'label'        => __( 'Yes, auto approve already approved commenters', 'secupress' ),
			'label_for'    => $field_name,
			'label_screen' => __( 'Yes, auto approve already approved commenters', 'secupress' ),
		),
	)
);

$field_name = $this->get_field_name( 'block-shortcodes' );
$this->add_field(
	__( 'Shortcode usage', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'A <a href="https://codex.wordpress.org/Shortcode" target="_blank">shortcode</a> can create macros to be used in a post\'s content.', 'secupress' ),
	),
	array(
		'depends'     => $main_field_name,
		array(
			'type'         => 'checkbox',
			'name'         => $field_name,
			'label'        => __( 'Yes, mark as spam any comment using any shortcode', 'secupress' ),
			'label_for'    => $field_name,
			'label_screen' => __( 'Yes, mark as spam any comment using any shortcode', 'secupress' ),
		),
		array(
			'type'         => 'helper_description',
			'name'         => $field_name,
			'description'  => __( '<em>BBcodes</em> and <em>shortcodes</em> are lookalikes, both will be blocked. A shortcode looks like <code>[this]</code>.', 'secupress' ),
		),
	)
);

$field_name = $this->get_field_name( 'better-blacklist-comment' );
$this->add_field(
	__( 'Improve the Blacklist Comments from WordPress', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'You can improve the list of bad words that will change some comment into a detected spam.', 'secupress' ),
	),
	array(
		'depends'     => $main_field_name,
		array(
			'type'         => 'checkbox',
			'name'         => $field_name,
			'label'        => __( 'Yes, i want to use a better blacklist comments to detect spams', 'secupress' ),
			'label_for'    => $field_name,
			'label_screen' => __( 'Yes, i want to use a better blacklist comments to detect spams', 'secupress' ),
		),
		array(
			'type'         => 'helper_description',
			'name'         => $field_name,
			'description'  => __( 'This will add more than 20,000 words in different languages.', 'secupress' ),
		),
	)
);

$field_name      = $this->get_field_name( 'remove_url' );
$this->add_field(
	__( 'Comment Author Url behavior', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'Some bots or people will just comment your blog to get a free and easy backling using the comment author url field.', 'secupress' ),
	),
	array(
		'depends'     => $main_field_name,
		array(
			'type'         => 'checkbox',
			'name'         => $field_name,
			'label'        => __( 'Yes, remove comment author url if the comment is not constructive enough.', 'secupress' ),
			'label_for'    => $field_name,
			'label_screen' => __( 'Yes, remove comment author url if the comment is not constructive enough.', 'secupress' ),
		),
		array(
			'type'         => 'helper_description',
			'name'         => $field_name,
			'description'  => __( 'Words that contains less than 4 characters are removed, then a blacklist is applied on the comment. At the end, if there is less than 5 words, the URL is removed.', 'secupress' ),
		),
	)
);

$field_name      = $this->get_field_name( 'mark-as' );
$this->add_field(
	__( 'Handling Spam', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'Usually WordPress keeps spam in the database, using the deletion setting, you will free some database storage usage.', 'secupress' ),
	),
	array(
		'depends'     => $main_field_name,
		array(
			'type'         => 'radios',
			'options'      => array(
									'deletenow'  => __( '<strong>Delete</strong> instantly any spam', 'secupress' ),
									'deletedays' => __( '<strong>Delete</strong> spam after 7 days', 'secupress' ),
									'markspam'   => __( '<strong>Only mark</strong> as spam', 'secupress' )
								),
			'default'      => 'deletenow',
			'name'         => $field_name,
			'label_for'    => $field_name,
			'label_screen' => __( 'How to mark spam', 'secupress' ),
		),
		array(
			'type'         => 'helper_help',
			'name'         => $field_name,
			'description'  => __( '"Mark as spam" will keep data in the database.', 'secupress' ),
		),
	)
);

$field_name      = $this->get_field_name( 'pings-trackbacks' );
$this->add_field(
	__( 'About Pings & Trackbacks', 'secupress' ),
	array(
		'name'        => $field_name,
		'description' => __( 'If you do not specially use pings and trackbacks, you can forbid the usage, on the contrary, never mark it as spam.', 'secupress' ),
	),
	array(
		'depends'     => $main_field_name,
		array(
			'type'         => 'radios',
			'options'      => array(
									'mark-ptb' => __( '<strong>Mark</strong> Pings & Trackbacks as spam like comments', 'secupress' ),
									'dontmark-ptb'  => __( '<strong>Never mark</strong> Pings & Trackbacks as spam', 'secupress' ),
									'forbid-ptb'   => __( '<strong>Fordib</strong> the usage of Pings & Trackbacks on this website', 'secupress' )
								),
			'default'      => 'mark-ptb',
			'name'         => $field_name,
			'label_for'    => $field_name,
			'label_screen' => __( 'What to do with Pings & Trackbacks', 'secupress' ),
		),
		array(
			'type'         => 'helper_description',
			'name'         => $field_name,
		),
	)
);


/* for info, will be marked as spam,:
url in name
known ips,
regular exp
local db
add_filter( 'preprocess_comment', 'baw_no_short_coms' );
function baw_no_short_coms( $comment )
{
	if ( is_user_logged_in() ) {
		return $comment;
	}
	$f = array( 'merci', 'génial', 'genial', 'wordpress', 'salut', 'bonjour', 'hello', 'post', 'article', 'pour', 'julio', 'super' );
	if ( ( $comment['comment_type'] == '' || $comment['comment_type'] == 'comment' ) &&
		count( array_filter( array_diff( array_unique( explode( ' ', strip_tags( strtolower( $comment['comment_content'] ) ) ) ), $f ), 'more_than_3_chars' ) )<5
	) {
		$comment['comment_author_url'] = '';
		$comment['comment_author'] = reset( explode( '@', $comment['comment_author'] ) );
	}
	return $comment;
}
<?php
// Disable X-Pingback HTTP Header.
add_filter('wp_headers', function($headers, $wp_query){
    if(isset($headers['X-Pingback'])){
        // Drop X-Pingback
        unset($headers['X-Pingback']);
    }
    return $headers;
}, 11, 2);
// Disable XMLRPC by hijacking and blocking the option.
add_filter('pre_option_enable_xmlrpc', function($state){
    return '0'; // return $state; // To leave XMLRPC intact and drop just Pingback
});
// Remove rsd_link from filters (<link rel="EditURI" />).
add_action('wp', function(){
    remove_action('wp_head', 'rsd_link');
}, 9);
// Hijack pingback_url for get_bloginfo (<link rel="pingback" />).
add_filter('bloginfo_url', function($output, $property){
    return ($property == 'pingback_url') ? null : $output;
}, 11, 2);
// Just disable pingback.ping functionality while leaving XMLRPC intact?
add_action('xmlrpc_call', function($method){
    if($method != 'pingback.ping') return;
    wp_die(
        'Pingback functionality is disabled on this Blog.',
        'Pingback Disabled!',
        array('response' => 403)
    );
});
?>
http://www.blacklistalert.org/?q=24.159.21.94 post
*/