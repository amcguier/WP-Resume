<?php
/*
Plugin Name: WP Resume
Plugin URI: http://ben.balter.com/2010/09/12/wordpress-resume-plugin/
Description: Out-of-the-box plugin which utilizes custom post types and taxonomies to add a snazzy resume to your personal blog or Web site. 
Version: 2.2.3
Author: Benjamin J. Balter
Author URI: http://ben.balter.com/
License: GPL2
*/

/**
 * @author Benjamin J. Balter
 * @shoutout Andrew Nacin (http://andrewnacin.com) for help with CPTs
 * @shoutout Andrew Norcross (http://andrewnorcross.com) for the drag-and-drop CSS
 * @shoutout Rvencu for help with WPML and multi-user prototyping
 * @shoutout Rodolfo Buaiz (http://www.brasofilo.com) for the translation help
 */

class WP_Resume {

	static $instance;
	public $version = '2.2.3';
	public $author = '';
	public $ttl = '3600';
	public $query_obj;

	function __construct() {
		
		self::$instance = &$this;
		
		//i18n
		add_action( 'init', array( &$this, 'i18n' ) );
		
		//cpt and CT
		add_action( 'init', array( &$this, 'register_cpt_and_t' ) );
		add_action( 'wp_resume_organization_add_form_fields', array( &$this, 'link_field' ) );
		add_action( 'wp_resume_organization_edit_form_fields', array( &$this, 'link_field' ), 10, 2 );
		add_action( 'create_wp_resume_organization', array( &$this, 'save_link_field' ) );
		add_action( 'edited_wp_resume_organization', array( &$this, 'save_link_field' ) );
		
		//ajax callbacks
		add_action('wp_ajax_add_wp_resume_section', array( &$this, 'ajax_add') );
		add_action('wp_ajax_add_wp_resume_organization', array( &$this, 'ajax_add') );
		add_action('wp_ajax_wp_resume_hide_donate', array( &$this, 'hide_donate') );
		
		//edit position screen
		add_action( 'save_post', array( &$this, 'save_wp_resume_position' ) );

		//frontend printstyles
		add_action( 'wp_print_styles', array( &$this, 'enqueue_styles' ) );

		//admin bar
		add_action( 'admin_bar_menu', array( &$this, 'admin_bar' ), 100 );

		//shortcode
		add_shortcode('wp_resume', array( &$this, 'shortcode' ) );
		
		//admin UI
		add_action( 'admin_menu', array( &$this, 'menu' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'wp_resume_organization_add_form', array( &$this, 'org_helptext' ) );
		add_action( 'admin_init', array( &$this, 'enqueue_scripts' ) );
		add_filter( 'option_page_capability_wp_resume_options', array( &$this, 'cap_filter' ), 10, 1 );
		
		//rewrites and redirects
		add_action( 'template_redirect',array( &$this, 'add_feeds' ) );
		add_action( 'init', array( &$this, 'rewrite_rules' ) );
		add_action( 'post_type_link', array( &$this, 'permalink' ), 10, 4 );
	
		//i18n
		add_filter( 'list_terms_exclusions', array( &$this, 'exclude_the_terms' ) );
		
		//plaintext resume
		add_filter( 'resume_plaintext_content', array( &$this, 'bulletit'), 5 );
		add_filter( 'resume_plaintext_content', 'wp_filter_nohtml_kses' );
		add_filter( 'resume_plaintext_content', 'stripslashes' );
		add_filter( 'resume_plaintext_content', array( &$this, 'html_entity_decode' ) );
		add_filter( 'resume_plaintext_title', array( &$this, 'html_entity_decode' ) );
		add_filter( 'resume_plaintext_title', 'stripslashes' );
		add_filter( 'resume_plaintext_location', array( &$this, 'format_plaintext_location' ) );
		add_filter( 'resume_plaintext_location', array( &$this, 'html_entity_decode' ) );
		add_filter( 'resume_plaintext_date', array( &$this, 'html_entity_decode' ) );
		add_filter( 'resume_plaintext_date', 'wp_filter_nohtml_kses' );
		add_filter( 'resume_plaintext_date', array( &$this, 'format_date_plaintext' ) );

	}
	 
	/**
	 * Registers the "resume block" custom post type and the the section and organization custom taxonomy
	 * @since 1.0a
	 */
	function register_cpt_and_t() {
	
		$options = $this->get_options();
		
		//Custom post type labels array
		$labels = array(
		'name' => _x('Positions', 'post type general name', 'wp-resume'),
		'singular_name' => _x('Resume Position', 'post type singular name', 'wp-resume'),
		'add_new' => __('Add New Position', 'wp-resume'),
		'add_new_item' => __('Add New Position', 'wp-resume'),
		'edit_item' => __('Edit Position', 'wp-resume'),
		'new_item' => __('New Position', 'wp-resume'),
		'view_item' => __('View Position', 'wp-resume'),
		'search_items' => __('Search Positions', 'wp-resume'),
		'not_found' =>  __('No Positions Found', 'wp-resume'),
		'not_found_in_trash' => __('No Positions Found in Trash', 'wp-resume'),
		'parent_item_colon' => '',
		'menu_name' => __('Resume', 'wp-resume' ),
		'all_items' => __('All Positions', 'wp-resume'),
	  );
	  
	  //Custom post type settings array
	  $args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true, 
		'menu_icon' => plugins_url( '/menu-icon.png', __FILE__ ),
		'query_var' => true,
		'rewrite' => ( isset( $options['rewrite'] ) && $options['rewrite'] ),
		'capability_type' => 'post',
		'hierarchical' => false,
		'menu_position' => null,
		'register_meta_box_cb' => array( &$this, 'meta_callback' ),
		'supports' => array( 'title', 'editor', 'revisions', 'custom-fields', 'page-attributes', 'author'),
		'taxonomies' => array('wp_resume_section', 'wp_resume_organization'),
	  ); 
	  
	  $args = apply_filters( 'wp_resume_cpt', $args );
	  
	  //Register the "wp_resume_position" custom post type
	  register_post_type( 'wp_resume_position', $args );
	  
		//Section labels array
		 $labels = array(
		   'name' => _x( 'Sections', 'taxonomy general name', 'wp-resume' ),
		   'singular_name' => _x( 'Section', 'taxonomy singular name', 'wp-resume' ),
		   'search_items' =>  __( 'Search Sections', 'wp-resume' ),
		   'all_items' => __( 'All Sections', 'wp-resume' ),
		   'parent_item' => __( 'Parent Section', 'wp-resume' ),
		   'parent_item_colon' => __( 'Parent Section:', 'wp-resume' ),
		   'edit_item' => __( 'Edit Section', 'wp-resume' ), 
		   'update_item' => __( 'Update Section', 'wp-resume' ),
		   'add_new_item' => __( 'Add New Section', 'wp-resume' ),
		   'new_item_name' => __( 'New Section Name', 'wp-resume' ),
		 ); 	
		 
		$args = apply_filters( 'wp_resume_section_ct', array( 'hierarchical' => true, 'labels' => $labels,  'query_var' => true, 'rewrite' => ( isset( $options['rewrite'] ) && $options['rewrite'] ) ? array( 'slug' => 'sections' ) : false ) ); 
		 
		//Register section taxonomy	
		register_taxonomy( 'wp_resume_section', 'wp_resume_position', $args );
		
		//orgs labels array
		$labels = array(
		   'name' => _x( 'Organizations', 'taxonomy general name', 'wp-resume' ),
		   'singular_name' => _x( 'Organization', 'taxonomy singular name', 'wp-resume' ),
		   'search_items' =>  __( 'Search Organizations', 'wp-resume' ),
		   'all_items' => __( 'All Organizations', 'wp-resume' ),
		   'parent_item' => __( 'Parent Organization', 'wp-resume' ),
		   'parent_item_colon' => __( 'Parent Organization:', 'wp-resume' ),
		   'edit_item' => __( 'Edit Organization', 'wp-resume' ), 
		   'update_item' => __( 'Update Organization', 'wp-resume' ),
		   'add_new_item' => __( 'Add New Organization', 'wp-resume' ),
		   'new_item_name' => __( 'New Organization Name', 'wp-resume' ),
		 ); 
		 
		$args = apply_filters( 'wp_resume_organization_ct', array( 'hierarchical' => true, 'labels' => $labels,  'query_var' => true, 'rewrite' => ( isset( $options['rewrite'] ) && $options['rewrite'] ) ? array( 'slug' => 'organizations' ) : false ) );
		 
		//Register organization taxonomy
		register_taxonomy( 'wp_resume_organization', 'wp_resume_position', $args );
		
	}


	/**
	 * Customizes the edit screen for our custom post type
	 * @since 1.0a
	 */
	function meta_callback() {

		//pull out the standard post meta box , we don't need it
		remove_meta_box( 'postcustom', 'wp_resume_position', 'normal' );
		
		//build our own section taxonomy selector using radios rather than checkboxes
		//We use the same callback for both taxonomies and just pass the taxonomy type as an argument
		add_meta_box( 'wp_resume_sectiondiv', __('Section', 'wp-resume'), array( &$this, 'taxonomy_box' ), 'wp_resume_position', 'side', 'low', array('type'=>'wp_resume_section') );
		
		//same with orgs 
		add_meta_box( 'wp_resume_organizationdiv', __('Organization', 'wp-resume'), array( &$this, 'taxonomy_box' ), 'wp_resume_position', 'side', 'low', array('type'=>'wp_resume_organization') ); 
		
		//build the date meta input box
		add_meta_box( 'dates', __('Date', 'wp-resume'), array( &$this, 'date_box' ), 'wp_resume_position', 'normal', 'high');
		
		//build custom order box w/ helptext
		add_meta_box( 'pageparentdiv', __('Resume Order', 'wp-resume'), array( &$this, 'order_box' ), 'wp_resume_position', 'side', 'low');
	
		do_action( 'position_metaboxes' );
		
	}

	/**
	 * Position metabox callback
	 * @param obj $post the post object
	 */
	function order_box($post) {
	?>
		<label class="screen-reader-text" for="menu_order"><?php _e('Order', 'wp-resume'); ?></label>
		<input type="text" name="menu_order" size="4" id="menu_order" value="<?php echo $post->menu_order; ?>">
		<p>
			<?php _e('Your resume will be sorted based on this number (ascending)', 'wp-resume'); ?>. <a href="#" id="wp_resume_help_toggle"><?php _e('More', 'wp-resume'); ?></a><br />
				<div id="wp_resume_help"><?php _e('When you add a new position, feel free to leave this number at "0" and a best guess will be made based on the position\'s end date (reverse chronological order)', 'wp-resume'); ?>. <br /><br /><?php _e('Of Course, you can always <a href="edit.php?post_type=wp_resume_position&page=wp_resume_options#sections">fine tune your resume order</a> on the options page', 'wp-resume');?>.</div>
			</p>
			
	<?php
	}

	/**
	 * Generates the taxonomy radio inputs 
	 * @since 1.0a
	 * @params object $post the post object WP passes
	 * @params object $box the meta box object WP passes (with our arg stuffed in there)
	 */
	function taxonomy_box( $post, $type ) {

		//pull the type out from the meta box object so it's easier to reference
		if ( is_array( $type) )
			$type = $type['args']['type'];
		
		//get the taxonomies details
		$taxonomy = get_taxonomy($type);
			
		//grab an array of all terms within our custom taxonomy, including empty terms
		$terms = get_terms( $type, array( 'hide_empty' => false ) );

		//garb the current selected term where applicable so we can select it
		$current = wp_get_object_terms( $post->ID, $type );
		
		//loop through the terms
		foreach ($terms as $term) {
			
			//build the radio box with the term_id as its value
			echo '<input type="radio" name="'.$type.'" value="'.$term->term_id.'" id="'.$term->slug.'"';
			
			//if the post is already in this taxonomy, select it
			if ( isset( $current[0]->term_id ) )
				checked( $term->term_id, $current[0]->term_id );
			
			//build the label
			echo '> <label for="'.$term->slug.'">' . $term->name . '</label><br />'. "\r\n";
		}
			echo '<input type="radio" name="'.$type.'" value="" id="none" ';
			checked( empty($current[0]->term_id) );
			echo '/> <label for="none">' . __('None', 'wp-resume') .'</label><br />'. "\r\n"; ?>
			
			<a href="#" id="add_<?php echo $type ?>_toggle">+ <?php echo $taxonomy->labels->add_new_item; ?></a>
			<div id="add_<?php echo $type ?>_div" style="display:none">
				<label for="new_<?php echo $type ?>"><?php echo $taxonomy->labels->singular_name; ?>:</label> 
				<input type="text" name="new_<?php echo $type ?>" id="new_<?php echo $type ?>" /><br />
	<?php if ($type == 'wp_resume_organization') { ?>
				<label for="new_<?php echo $type ?>_location" style="padding-right:24px;"><?php _e('Location', 'wp_resume'); ?>:</label> 
				<input type="text" name="new_<?php echo $type ?>_location" id="new_<?php echo $type ?>_location" /><br />
	<?php } ?>
				<input type="button" value="Add New" id="add_<?php echo $type ?>_button" />
				<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" id="<?php echo $type ?>-ajax-loading" style="display:none;" alt="" />
			</div>
	<?php
		//nonce is a funny word
		wp_nonce_field( 'add_'.$type, '_ajax_nonce-add-'.$type );
		wp_nonce_field( 'wp_resume_taxonomy', 'wp_resume_nonce'); 
	}

	/**
	 * Processes AJAX request to add new terms
	 * @since 1.2
	 */
	function ajax_add() {
		
		//pull the taxonomy type (section or organization) from the action query var
		$type = substr($_GET['action'],4);
		
		//pull up the taxonomy details
		$taxonomy = get_taxonomy($type);
		
		//check the nonce
		check_ajax_referer( $_GET['action'] , '_ajax_nonce-add-' . $taxonomy->name );
		
		//check user capabilities
		if ( !current_user_can( $taxonomy->cap->edit_terms ) )
			die('-1');

		//insert term
		$desc = ( isset( $_POST['new_'. $type . '_location'] ) ) ? $_POST['new_'. $type . '_location'] : '';
		$term = wp_insert_term( $_POST['new_'. $type], $type, array('description' => $desc ) );
		
		//catch errors
		if ( is_wp_error( $term ) ) {
			$this->taxonomy_box( $post, $type );
			exit();
		}
		
		//associate position with new term
		wp_set_object_terms( $_POST['post_ID'], $term['term_id'], 'wp_resume_section' );
		
		if ( $type == 'section' ) {
			$user = wp_get_current_user();
			$author = $user->user_nicename;
			wp_cache_delete( $author . '_sections', 'wp_resume' );
			wp_cache_delete( $author . '_sections_hide_empty', 'wp_resume' );
			$this->flush_cache();
		}
		
		//get updated post to send to taxonomy box
		$post = get_post( $_POST['post_ID'] );
		
		//return the HTML of the updated metabox back to the user so they can use the new term
		$this->taxonomy_box( $post, $type );
		
		exit();
	}

	/**
	 * Generates our date custom metadata box
	 * @since 1.0a
	 * @params object $post the post object WP passes
	 */
	function date_box( $post ) {	

		//pull the current values where applicable
		$from = get_post_meta( $post->ID, 'wp_resume_from', true );
		$to = get_post_meta( $post->ID, 'wp_resume_to', true );
		
		//format and spit out
		echo '<label for="from">' . __( 'From', 'wp-resume' ) . '</label> <input type="text" name="from" id="from" value="'.$from.'" placeholder="e.g., May 2011"/> ';
		echo '<label for="to">' . __( 'To', 'wp-resume' ) . '</label> <input type="text" name="to" id="to" value="'.$to.'" placeholder="e.g., Present" />';

	}

	/**
	 * Saves our custom taxonomies and date metadata on post add/update
	 * @since 1.0a
	 * @params int $post_id the ID of the current post as passed by WP
	 */
	function save_wp_resume_position( $post_id ) {
	
		//Verify our nonce, also varifies that we are on the edit page and not updating elsewhere
		if ( !isset( $_POST['wp_resume_nonce'] ) || !wp_verify_nonce( $_POST['wp_resume_nonce'], 'wp_resume_taxonomy' , 'wp_resume_nonce' ) )
			return $post_id;
				
		//If we're autosaving we don't really care all that much about taxonomies and metadata
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return $post_id;
			
		//If this is a post revision and not the actual post, kick
		//(the save_post action hook gets called twice on every page save)
		if ( wp_is_post_revision($post_id) )
			return $post_id;
		
		//Verify user permissions
		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;
				
		//Associate the wp_resume_position with our taxonomies
		wp_set_object_terms( $post_id, (int) $_POST['wp_resume_section'], 'wp_resume_section' );
		wp_set_object_terms( $post_id, (int) $_POST['wp_resume_organization'], 'wp_resume_organization' );
		
		//update the posts date meta
		update_post_meta( $post_id, 'wp_resume_from', $_POST['from'] );
		update_post_meta( $post_id, 'wp_resume_to', $_POST['to'] );
					
		//If they did not set a menu order, calculate a best guess bassed off of chronology
		//(menu order uses the posts's menu_order field and is 1 bassed by default)
		if ($_POST['menu_order'] == 0) {
			
			//grab the DB Obj.
			global $wpdb;
		
			//calculate the current timestamp
			$timestamp = strtotime( $_POST['to'] );
			if ( !$timestamp ) $timestamp = time();
			
			//set a counter
			$new_post_position = 1;
			
			//loop through posts 
			$section = get_term ($_POST['wp_resume_section'], 'wp_resume_section');
			$args = array(
				'post_type' => 'wp_resume_position',
				'orderby' => 'menu_order',
				'order' => 'ASC',
				'numberposts' => -1,
				'wp_resume_section' =>	$section->slug,
				'exclude' => $post_id
			);
			$posts = get_posts( $args );

			foreach ($posts as $post) {
		
				//build timestamp of post we're checking
				$ts_check = strtotime( get_post_meta( $post->ID, 'wp_resume_to', true) );
				if (!$ts_check) 
					$ts_check = time();
				
				//If we've inserted our new post in the menu_order, increment all subsequent positions
				if ($new_post_position != 1)
					//manually update the post b/c calling wp_update_post would create a recurssion loop
					$wpdb->update($wpdb->posts,array('menu_order'=>$post->menu_order+1),array('ID'=>$post->ID));
				
				//If the new post's timestamp is earlier than the current post, stick the new post here
				if ($timestamp <= $ts_check && $new_post_position == 1) 
					$new_post_position = $post->menu_order + 1;	
			
			}
			
			//manually update the post b/c calling wp_update_post would create a recurssion loop
			$wpdb->update($wpdb->posts,array('menu_order'=>$new_post_position),array('ID'=>$post_id));
			
		}

		$user = wp_get_current_user();
		wp_cache_delete(  $user->user_nicename . '_resume', 'wp_resume' );
		wp_cache_delete(  $post_id . '_organization', 'wp_resume' );
		$this->flush_cache();

	}
	
	/**
	 * Depricated for consistency
	 */
	function format_date( $ID ) {
		_deprecated_function( __FUNCTION__, '2.0.4 of WP Resume', 'get_date' );
		return $this->get_date( $ID );
	}

	/**
	 * Function used to parse the date meta and move to human-readable format
	 * @since 1.0a
	 * @param int $ID post ID to generate date for
	 */
	function get_date( $ID ) {

		//Grab from and to post meta
		$from = get_post_meta( $ID, 'wp_resume_from', true ); 
		$to = get_post_meta( $ID, 'wp_resume_to', true ); 
		
		//if we have a start date, format as "[from] - [to]" (e.g., May 2005 - May 2006)
		if ( $from ) 
			$date = '<span class="dtstart" title="' . date( 'Y-m-d', strtotime( $from ) ) . '">' . $from . '</span> &ndash; <span class="dtend" title="' . date( 'Y-m-d', strtotime( $to ) ) . '">' . $to . '</span>';
		
		//if we only have a to, just pass back the to (e.g., "May 2015")
		else if ( $to ) 
			$date= '<span class="dtend" title="' . date( 'Y-m-d', strtotime( $to ) ) . '">' . $to . '</span>';
		
		//If there's no date meta, just pass back an empty string so we dont generate errors
		else 
			$date = '';
			
		return apply_filters( 'wp_resume_date', $date, $ID, $from, $to );
		
	}

	/**
	 * Takes the section term taxonomy and re-keys it to the user specified order
	 * @returns array of term objects in user-specified order
	 * @since 1.0a
	 */
	function get_sections( $hide_empty = true, $author = '' ) {

		//init array
		$output = array();
		
		//set default author
		if ($author == '') {
			$user = wp_get_current_user();
			$author = $user->user_nicename;
		}
		
		if ( is_int( $author ) ) {
			$user = get_userdata( $author );
			$author = $user->user_nicename;
		}
		
		$cache_slug = $author . '_sections';
		if ( $hide_empty )
			$cache_slug .= '_hide_empty';
			
		if ($cache = wp_cache_get( $cache_slug, 'wp_resume' ) )
			return $cache;

		//get all sections ordered by term_id (order added)
		$sections = get_terms( 'wp_resume_section', array('hide_empty' => $hide_empty ) );
		
		//get the plugin options array to pull the user-specified order
		$options = $this->get_options();
		
		//pull out the order array
		$user_options = $this->get_user_options($author);
		
		//user has not specified any sections, prevents errors on initial activation
		if ( !isset( $user_options['order'] ) )
			return apply_filters('wp_resume_sections', $sections );

		$section_order = $user_options['order'];
			
		//Loop through each section
		foreach( $sections as $ID => $section ) { 
			
			//if the term is in our order array
			if ( is_array($section_order) && array_key_exists( $section->term_id, $section_order ) ) { 
			
				//push the term object into the output array keyed to it's order
				$output[ $section_order[$section->term_id] ] = $section;
			
				//pull the term out of the original array
				unset($sections[$ID]);
			
			}
		}
		
		//for those terms that we did not have a user-specified order, stick them at the end of the output array
		foreach($sections as $section) $output[] = $section;
		
		//sort by key
		ksort($output);

		$output = apply_filters('wp_resume_sections', $output);
					
		wp_cache_set( $cache_slug, $output, 'wp_resume', $this->ttl );
						
		//return the new array keyed to order
		return $output;
		
	}

	/**
	 * Queries for all the resume blocks within a given section
	 * @params string $section section slug
	 * @returns array array of post objects
	 * @since 1.0a
	 */
	function query( $section, $author = '' ) {
		
		//if the author isn't passed as a function arg, see if it has been set by the shortcode
		if ( $author == '' && isset( $this->author ) )
			$author = $this->author;
								
		//build our query
		$args = array(
			'post_type' => 'wp_resume_position',
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'nopaging' => true,
			'wp_resume_section' => $section,
		);
		
		if ( is_int( $author ) )
			$args['author'] = $author;		
		else
			$args['author_name'] = $author;

		
		$args = apply_filters('wp_resume_query_args', $args);
			
		//query and return
		$this->query_obj = new wp_query($args);
		return $this->query_obj;
	}

	/**
	 * Retrieves the org associated with a given position
	 * @since 1.1a
	 */
	function get_org( $postID ) {

		if ( $cache = wp_cache_get( $postID . '_organization', 'wp_resume' ) )
			return $cache;
		
		$organization = wp_get_object_terms( $postID, 'wp_resume_organization' );

		if ( is_wp_error( $organization ) || !isset( $organization[0] ) ) 
			return false;
		
		$org = apply_filters( 'resume_organization', $organization[0] );
		
		wp_cache_set( $postID . '_organization', $org, 'wp_resume', $this->ttl );
		
		return $org;
		
	}

	/**
	 * Get's the options
	 * @since 1.2
	 */
	function get_options() {
		$options = get_option('wp_resume_options');
		return apply_filters( 'wp_resume_options', $options );
	}
	
	/**
	 * Saves global options
	 * @param $data, some or all option fields
	 * @return bool success/fail
	 */
	function update_options( $data, $merge = true ) {
		
		if ( $merge ) {
			$defaults = $this->get_options();
			$data = wp_parse_args( $data, $defaults );	
		}
		
		return update_option( 'wp_resume_options', $data );
	
	}

	/**
	 * Gets wp_resume usermeta field
	 * @param int|string $user username or ID to retrieve
	 * @since 1.6
	 */
	function get_user_options( $user = '' ) {

		if ( $user == '' )
			$user = $this->author;
			
		//get ID if we have a username
		if ( !is_int($user) ) {

			$userdata =	get_user_by('slug', $user);
			
			if ( !$userdata )
				return array();
				
			$user = $userdata->ID;
			
		}

		return apply_filters( 'wp_resume_user_options', get_user_option( 'wp_resume', (int) $user ), $user );

	} 
	
	function update_user_options( $user, $value ) {
		update_user_option( $user, 'wp_resume', $value );
	} 
	
	/**
	 * Retrieves an organization's link, if any
	 * @param int $org the org ID
	 * @return string the org link
	 */
	function get_org_link( $org ) {
		
		$link = get_option( 'wp_resume_organization_link_' . (int) $org );
		return apply_filters( 'wp_resume_organization_link', $link, $org );
	
	}
	
	/**
	 * Stores an organization's link
	 * @param int $org the org ID
	 * @return bool success/fail
	 */
	function set_org_link( $org, $link ) {
	
		return update_option( 'wp_resume_organization_link_' . (int) $org, esc_url( $link ) );
	
	}
	
	/**
	 * Flushes all wp-resume data from the object cache, if it exists
	 */ 
	function flush_cache() {
		global $wp_object_cache;
		unset( $wp_object_cache->cache['wp_resume']);
	}
	
	/**
	 * Loops through all posts in a given query to determine if any contain the resume shortcode
	 * @returns bool true if found, otherwise false
	 */
	function resume_in_query() {
	
		global $wp_query;

		if ( $cache = wp_cache_get( 'query_' . $wp_query->query_vars_hash, 'wp_resume' ) )
			return $cache;
				
		$enqueue = false;
		while ( have_posts() ): the_post();
		
			global $post;
			
			//if post is a position, we should load CSS
			if ( $post->post_type == 'position' )
				$enqueue = true;
				
			//post is a post/page, but has shortcode, so load CSS
			else if ( preg_match( '/\[wp_resume([^\]]*)]/i', get_the_content() ) != 0)
				$enqueue = true;
			
		endwhile;
		
		wp_reset_query();
		
		wp_cache_set( 'query_' . $wp_query->query_vars_hash, $enqueue, 'wp_resume', $this->ttl );
				
		return $enqueue;
		
	}
	
	/**
	 * Adds links to the admin bar
	 * @since 2.0.3
	 */
	function admin_bar() {	
		global $wp_admin_bar;
		
	    if ( !is_admin_bar_showing() )
    	  return;
    	  
    	if ( !is_single() && !is_page() )
    		return;
		
  		if ( !$this->resume_in_query() )
  			return;
  			
  		global $post;
  		
  		if ( $post->post_author != get_current_user_id() && !current_user_can( 'edit_others_posts' ) )
  			return;
    
		$wp_admin_bar->add_menu( array( 
				'id' => 'wp-resume', 
				'title' => __( 'Edit Resume', 'wp-resume' ), 
				'href' => admin_url('edit.php?post_type=wp_resume_position'),
			) );
		
		$wp_admin_bar->add_menu( array( 
				'parent' => 'wp-resume', 
				'id' => 'wp-resume-options', 
				'title' => __( 'Resume Options', 'wp-resume' ), 
				'href' => admin_url( 'edit.php?post_type=wp_resume_position&page=wp_resume_options' ), 
			) );
		
	}

	/**
	 * Adds custom CSS to WP's queue
	 * Checks to see if file 'resume-style.css' exists in the current template directory, otherwise includes default
	 * @since 1.0a
	 */
	function enqueue_styles() {
		
		if ( !$this->resume_in_query() )
			return;
	
		add_filter( 'post_class', array( &$this, 'add_post_class' ) );

		if ( file_exists ( get_stylesheet_directory() . '/resume-style.css' ) )
			wp_enqueue_style('wp-resume-custom-stylesheet', get_stylesheet_directory_uri() . '/resume-style.css' );
		else 
			wp_enqueue_style('wp-resume-default-stylesheet', plugins_url(  'css/resume-style.css', __FILE__ ) );
	}
	
	/**
	 * Adds resume class to div, optionally adds class to hide the title
	 * @param array $classes the classes as originally passed
	 * @returns array $classes the modified classes array
	 */
	function add_post_class( $classes ) {
		global $post;

		if ( preg_match( '/\[wp_resume([^\]]*)]/i', get_the_content() ) == false )
			return $classes;
			
		$classes[] = 'resume';
		
		$options = &$this->get_options();
		if ( isset( $options['hide-title'] ) && $options['hide-title'] )
			$classes[] = 'hide-title';
				
		return $classes;

	}

	/**
	 * Adds an options submenu to the resume menu in the admin pannel
	 * @since 1.0a
	 */
	function menu() {
		
		add_submenu_page( 'edit.php?post_type=wp_resume_position', __('Resume Options', 'wp-resume'), __('Options', 'wp-resume'), 'edit_posts', 'wp_resume_options', array( &$this, 'options' ) );

	}

	/**
	 * Valdidates options submission data and stores position order
	 * @params array $data post data
	 * @since 1.5
	 * @returns array of validated data (without position order)
	 */
	function options_validate($data) {

		//make sure we're POSTing
		if ( empty($_POST) )
			return $data;
			
		//grab the existing options, we must hand WP back a complete option array
		$options = $this->get_options();
		
		//figure out what user we are acting on
		global $wpdb;
		$authors = 	get_users( array( 'blog_id' => $GLOBALS['blog_id'] ) );

		if ( !current_user_can('edit_others_posts') ) {
			
			$current_author = get_current_user_id();
			
		} else if ( sizeof($authors) == 1 ) {
		
			//if there is only one user in the system, it's gotta be him
			$current_author = $authors[0]->ID;
		
		} else if ( $_POST['old_user'] != $_POST['user'] ) {
		
			//if this is an auto save as a result of the author dropdown changing, 
			//save as old author, not author we're moving to
			$current_author = $_POST['old_user'];
			
			//Because we post to options.php and then get redirected, 
			//trick WP into appending the user as a parameter so we can update the dropdown
			//goes through wp_safe_redirect, so no need to escape, right?
			$_REQUEST['_wp_http_referer'] .= '&user=' . $_POST['user'];
			
		} else {
		
			//if this is a normal submit, just grab the author from the dropdown
			$current_author = $_POST['user'];
		
		}

		$user_options = $this->get_user_options( (int) $current_author );

		//start with a blank array to remove empty fields
		$user_options['contact_info'] = array();
		
		//strip html from fields
		$user_options['name'] = wp_filter_nohtml_kses( $data['name'] );
		$user_options['summary'] = wp_filter_post_kses( $data['summary'] );

		foreach ($data['contact_info_field'] as $id=>$value) {
			
			$field = explode('|',$data['contact_info_field'][$id]);

			if ( !$value || !$id ) 
				continue;
							
			if ( sizeof($field) == 1)
				$user_options['contact_info'][$field[0]] = wp_filter_post_kses( $data['contact_info_value'][$id] );
			else
				$user_options['contact_info'][$field[0]][$field[1]] = wp_filter_post_kses( $data['contact_info_value'][$id] );

		}

		//sanitize section order data
		foreach ($data['order'] as $key=>$value)
			$user_options['order'][$key] = intval( $value );
		
		//store position order data
		if ( isset($data['position_order'] )  && is_array($data['position_order'] ) ) { 
			foreach ($data['position_order'] as $positionID => $order) {
				$post['ID'] = intval( $positionID );
				$post['menu_order'] = intval( $order );
				wp_update_post( $post );
			}
		}
		
		if ( current_user_can( 'manage_options' ) ) {		
			//move site-wide fields to output array
			$fields = array( 'fix_ie', 'rewrite', 'hide-title' );
			foreach ($fields as $field) {
				$options[$field] = $data[$field];
			}
		}
			
		//store usermeta
		$user = get_userdata( $current_author );
		$this->update_user_options( $user->ID, $user_options);
	
		wp_cache_delete(  $user->user_nicename . '_sections', 'wp_resume' );
		wp_cache_delete(  $user->user_nicename . '_sections_hide_empty', 'wp_resume' );
		wp_cache_delete(  $user->user_nicename . '_resume', 'wp_resume' );
		$this->flush_cache();
		
		//flush in case they toggled rewrite
		flush_rewrite_rules();
		
		$options = apply_filters('wp_resume_options', $options);

		return $options;
	}
	
	/**
	 * Returns default contact fields, i18n'd and filtered
	 * @returns array contact fields
	 */
	function contact_fields() {
		$fields = array( 
			'email' => __('E-Mail', 'wp-resume'),
			'tel' => __('Phone', 'wp-resume'),
			'other' => __('Other', 'wp-resume'),
			'adr' => array( 
						'street-address' => __('Street Address', 'wp-resume'),
						'locality' => __('City/Locality', 'wp-resume'),
						'region' => __('State/Region', 'wp-resume'),
						'postal-code' => __('Zip/Postal Code', 'wp-resume'),
						'country-name' => __('Country', 'wp-resume'),
					),
		);
		
		$fields = apply_filters('wp_resume_contact_fields', $fields );
		
		return $fields;
	}


	/**
	 * Callback to generate a contact info row for the options page
	 * @param int|string $value the field's current value
	 * @param int $field_id the id of the field to output
	 */
	function contact_info_row( $value = '', $field_id = '' ) { ?>
		<li id="contact_info_row[]" class="contact_info_row">
			<select name="wp_resume_options[contact_info_field][]" id="contact_info_field[]">
			<option></option>
			<?php 	foreach ( $this->contact_fields() as $id => $field) { ?>
					<?php 	if ( is_array($field) ) {
								foreach ($field as $subid => $subfield) { ?>
									<option value="<?php echo $id . '|' . $subid; ?>" <?php selected($field_id, $subid);?>>
										<?php echo $subfield; ?>
									</option>				
								<?php }
							} else { ?>
								<option value="<?php echo $id; ?>" <?php selected($field_id, $id);?>><?php echo esc_attr( $field ); ?></option>	
							<?php } ?>
			<?php } ?>
			</select>
			<input type="text" name="wp_resume_options[contact_info_value][]" id="contact_info_value[]" value="<?php echo $value; ?>"/> <br />
		</li>
	<?php } 

	/**
	 * Creates the options sub-panel
	 * @since 1.0a
	 */
	function options() { 	
		global $wpdb;
	?>
	<div class="wp_resume_admin wrap">
		<h2><?php _e('Resume Options', 'wp_resume'); ?></h2>
		<form method="post" action='options.php' id="wp_resume_form">
	<?php 
			
	//provide feedback
	settings_errors();

	//Tell WP that we are on the wp_resume_options page
	settings_fields( 'wp_resume_options' ); 

	//Pull the existing options from the DB
	$options = $this->get_options();

	//set up the current author
	$authors = get_users( array( 'blog_id' => $GLOBALS['blog_id'] ) );

	if ( !current_user_can('edit_others_posts') ) {
		$user = wp_get_current_user();
		$current_author = $user->ID;	
	} else if ( sizeof($authors) == 1 ) {
		//if there's only one author, that's our author
		$current_author = $authors[0]->ID;
	} else if ( isset($_GET['user'] ) ) {
		//if there's multiple authors, look for post data from author drop down
		$current_author = $_GET['user'];
	} else {
		//otherwise, assume the current user
		$current_user = wp_get_current_user();
		$current_author = $current_user->ID;
	}

	$user_options = $this->get_user_options( (int) $current_author );

	?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Usage', 'wp-resume'); ?></label></th>
				<td>
					<strong><?php _e('To use WP Resume...', 'wp-resume'); ?></strong>
					<ol>
						<li><?php _e('Add content to your resume through the menus on the left', 'wp-resume'); ?></li>
						<li><?php _e('If you wish, add your name, contact information, summary, and order your resume below', 'wp-resume'); ?></li>
						<li><?php _e('Create a new page as you would normally', 'wp-resume'); ?>
						<li><?php _e('Add the text <code>[wp_resume]</code> to the page\'s body', 'wp-resume'); ?></li>
						<li><?php _e('Your resume will now display on that page', 'wp-resume'); ?>.</li>
					</ol>
					<?php if ( current_user_can( 'edit_others_posts' ) ) { ?>
					<br />
					<strong><?php _e('Want to have multiple resumes on your site?', 'wp-resume'); ?></strong> <a href="#" id="toggleMultiple"><?php _e('Yes!', 'wp-resume'); ?></a><br />
					<div id="multiple">
					<?php _e('WP Resume associates each resume with a user. To create a second resume...', 'wp-resume'); ?>
					<ol>
						<li style="font-size: 11px;"><?php _e('Simply <a href="user-new.php">add a new user</a> (or select an existing user in step two)', 'wp-resume'); ?>.</li>
						<li style="font-size: 11px;"><a href="post-new.php?post_type=wp_resume_position"><?php _e('Add positions</a> as you would normally, being sure to select that user as the position\'s author. You may need to display the author box by enabling it in the "Screen Options" toggle in the top-right corner of the position page', 'wp-resume'); ?>.</li>
						<li style="font-size: 11px;"><?php _e('Select the author from the drop down below and fill in the name, contact info, and summary fields (optional)', 'wp-resume'); ?>.</li>
						<li style="font-size: 11px;"><a href="post-new.php?post_type=page"><?php _e('Create a new page</a> and add the <code>[wp_resume]</code> shortcode, similar to above, but set the page author to the resume\'s author (the author from step two). Again, you may need to enable the author box', 'wp-resume'); ?>.</li>
					</ol>
					 <em><?php _e('Note', 'wp_resume'); ?>:</em> <?php _e('To embed multiple resumes on the same page, you can alternatively use the syntax <code>[wp_resume user="user_nicename"]</code> where <code>user_nicename</code> is the username of the resume\'s author', 'wp-resume'); ?>.
					 <?php } ?>
					 </div>
				</td>
			</tr>
			<?php 
				if ( sizeof($authors) > 1 && current_user_can( 'edit_others_posts' ) ) {
				?>
			<tr valign="top">
				<th scope="row"><?php _e('User', 'wp_resume'); ?></label></th>
				<td>
					<?php wp_dropdown_users( array( 'selected' => $current_author ) ); ?>
					<input type="hidden" name="old_user" value="<?php echo $current_author; ?>" />
				</td>
			</tr>
			<?php } ?>
			<tr valign="top">
				<th scope="row"><label for="wp_resume_options[name]"><?php _e('Name', 'wp-resume') ;?></label></th>
				<td>
					<input name="wp_resume_options[name]" type="text" id="wp_resume_options[name]" value="<?php if ( isset( $user_options['name'] ) ) echo $user_options['name']; ?>" class="regular-text" /><BR />
					<span class="description"><?php _e('Your name -- displays on the top of your resume', 'wp-resume'); ?>.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Contact Information', 'wp-resume'); ?></th>
				<td>
					<ul class="contact_info_blank" style="display:none;">
						<?php $this->contact_info_row(); ?>
					</ul>
					<ul id="contact_info">
						<?php if ( isset($user_options['contact_info'] ) && is_array( $user_options['contact_info'] ) ) 
							array_walk_recursive($user_options['contact_info'], array( &$this, 'contact_info_row' ) ); ?>
					</ul>
					<a href="#" id="add_contact_field">+ <?php _e('Add Field', 'wp-resume'); ?></a><br />
					<span class="description"><?php _e('(optional) Add any contact info you would like included in your resume', 'wp-resume'); ?>.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="wp_resume_options[summary]"><?php _e('Summary', 'wp-resume'); ?></label></th>
				<td id="poststuff">
				<div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv'; ?>" class="postarea">
					<?php the_editor( ( isset($user_options['summary'] ) ) ? $user_options['summary'] : '', "wp_resume_options[summary]" ); ?>	
				</div>
				<span class="description"><?php _e('(optional) Plain-text summary of your resume, professional goals, etc. Will appear on your resume below your contact information before the body', 'wp-resume'); ?>.</span>	
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Resume Order', 'wp-resume'); ?></th>
				<td>
				<?php $this->order_dragdrop( (int) $current_author ); ?>
				<span class="description"><?php _e('New positions are automatically displayed in reverse chronological order, but you can fine tune that order by rearranging the elements in the list above', 'wp-resume'); ?>.</span>
				</td>
			</tr>
			<?php if ( current_user_can( 'manage_options' ) ) { ?>
			<tr valign="top">
				<th scope="row">
					<?php _e( 'Advanced Options', 'wp-resume' ); ?>
				</th>
				<td>
					<a href="#" id="toggleHood"><?php _e('Show Advanced Options', 'wp-resume'); ?></a>
				</td>
			</tr>
			<tr valign="top" class="underHood">
				<th scrope="row"><?php _e('Force IE HTML5 Support', 'wp-resume'); ?></th>
				<td>
					<input type="radio" name="wp_resume_options[fix_ie]" id="fix_ie_yes" value="1" <?php checked($options['fix_ie'], 1); ?>/> <label for="fix_ie_yes"><?php _e('Yes', 'wp-resume'); ?></label><br />
					<input type="radio" name="wp_resume_options[fix_ie]" id="fix_ie_no" value="0" <?php checked($options['fix_ie'], 0); ?>/> <label for="fix_ie_no"><?php _e('No', 'wp-resume'); ?></label><br />
					<span class="description"><?php _e('If Internet Explorer breaks your resume\'s formatting, conditionally including a short Javascript file should force IE to recognize html5 semantic tags', 'wp-resume'); ?>.</span>
				</td>
			</tr>
			<tr valign="top" class="underHood">
				<th scrope="row"><?php _e('Hide Page Title', 'wp-resume'); ?></th>
				<td>
					<input type="radio" name="wp_resume_options[hide-title]" id="hide_yes" value="1" <?php checked($options['hide-title'], 1); ?>/> <label for="hide_yes"><?php _e('Yes', 'wp-resume'); ?></label><br />
					<input type="radio" name="wp_resume_options[hide-title]" id="hide_no" value="0" <?php checked($options['hide-title'], 0); ?> <?php checked($options['hide-title'], null); ?>/> <label for="hide_no"><?php _e('No', 'wp-resume'); ?></label><br />
					<span class="description"><?php _e('Hides the standard page title on pages (or posts) containing the <code>[wp_resume]</code> shortcode by adding a <code>hide-title</code> class', 'wp-resume'); ?>.</span>
				</td>
			</tr>
			<tr valign="top" class="underHood">
				<th scrope="row"><?php _e('Enable URL Rewriting', 'wp-resume'); ?></th>
				<td>
					<input type="radio" name="wp_resume_options[rewrite]" id="rewrite_yes" value="1" <?php checked($options['rewrite'], 1); ?>/> <label for="rewrite_yes"><?php _e('Yes', 'wp-resume'); ?></label><br />
					<input type="radio" name="wp_resume_options[rewrite]" id="rewrite_no" value="0" <?php checked($options['rewrite'], 0); ?> <?php checked($options['rewrite'], null); ?>/> <label for="rewrite_no"><?php _e('No', 'wp-resume'); ?></label><br />
					<span class="description"><?php _e('Creates individual pages for each position, and index pages for each section and organization', 'wp-resume'); ?>.</span>
				</td>
			</tr>
			<tr valign="top" class="underHood">
				<th scrope="row"><?php _e('Customizing WP Resume', 'wp-resume'); ?></th>
				<td>
					<Strong><?php _e('Style Sheets', 'wp-resume'); ?></strong><br />
					<?php _e('Although some styling is included by default, you can customize the layout by modifying <a href="theme-editor.php">your theme\'s stylesheet</a>', 'wp-resume'); ?>.<br /><br />
					
					<strong><?php _e('Templates', 'wp-resume'); ?></strong> <br />
					<?php _e("Any WP Resume template file (resume.php, resume-style.css, resume-text.php, etc.) found in your theme's directory will override the plugin's included template. Feel free to copy the file from the plugin directory into your theme's template directory and modify the file to meet your needs", 'wp-resume'); ?>.<br /><br />
					
					<strong><?php _e('Feeds', 'wp-resume'); ?></strong> <br />
					<?php _e('WP Resume allows you to access your data in three machine-readable formats. By default, the resume outputs in an <a href="http://microformats.org/wiki/hresume">hResume</a> compatible format. A JSON feed can be generated by appending <code>?feed=json</code> to your resume page\'s URL and a plain-text alternative (useful for copying and pasting into applications and forms) is available by appending <code>?feed=text</code> to your resume page\'s URL', 'wp-resume'); ?>.
				</td>
			</tr>
			<?php if ( !isset( $user_options['hide-donate'] ) || $user_options['hide-donate'] != true ) { ?>
			<tr valign="top" id="donate">
				<th scope="row">
					<?php _e( 'Support', 'wp-resume' ); ?>
				</th>
				<td>
					<em><?php _e('Enjoy using WP Resume? Please consider <a href="http://ben.balter.com/donate/">making a small donation</a> to support the software\'s continued development.', 'wp-resume'); ?></em> <span style="font-size: 10px;">(<a href="#" id="hide-donate"><?php _e( 'hide this message', 'wp-resume'); ?></a>)</span>
					<?php wp_nonce_field( 'wp_resume_hide_donate', '_ajax_nonce-wp-resume-hide-donate' ); ?>

				</td>
			</tr>
			<?php } //end if donate hidden ?>
			<?php } //end if manage_options ?>				
		</table>
		<p class="submit">
			 <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'wp-resume') ?>" />
		</p>
		</form>
	</div>
	<?php
	}
	
	/**
	 * Outputs the dragable ordering UI
	 * @param string $current_author the current author
	 * @since 2.0.5
	 * @uses dragdrop_section
	 *
	 * Structure:
	 *
	 * ul.sections
	 * 	li.section
	 * 		ul.organizations
	 *			li.organization
	 *				ul.positions
	 * 					li.position
	 */
	function order_dragdrop( $current_author ) {
	?>
		<ul id="sections">
			<?php //loop through the user's non-empty section
				foreach ( $this->get_sections( true, $current_author ) as $section )	
					$this->dragdrop_section ( $current_author, $section );
			?>
		</ul><!-- #sections -->
	<?php
	}
	
	/**
	 * Outputs one section of the dragdrop UI
	 * @param string $current_author the current author
	 * @param object $section the current section
	 * @since 2.0.5
	 * @uses dragrop_position
	 * @uses get_previous_org
	 * @uses get_next_org
	 * @uses dragdrop_org_start
	 * @uses dragdrop_org_end
	 */
	function dragdrop_section( $current_author, $section ) { ?>
		<li class="section" id="<?php echo $section->term_id; ?>">
			<a href="<?php echo admin_url( 'edit-tags.php?action=edit&taxonomy=wp_resume_section&tag_ID=' . $section->term_id . '&post_type=wp_resume_position' ); ?>">
				<?php echo $section->name; ?>
			</a>
			<ul class="organizations">
				<?php 
				
				//get all positions in this section and loop
				$posts = $this->query( $section->slug, $current_author );
				if ( $posts->have_posts() ) : while ( $posts->have_posts() ) : $posts->the_post();
					
					//grab the current position's organization and compare to last
					//if different or this is the first position, output org label and UL
					$org = $this->get_org( get_the_ID() );
					if ( $org && $this->get_previous_org( ) != $org )
						$this->dragdrop_org_start( $org );
					
					//main position li	 
					$this->dragdrop_position();
					
					//next position's organization is not the same as this 
					//or this is the last position in the query
					if ( $org && $this->get_next_org() != $org )
						$this->dragdrop_org_end();
					
				endwhile; endif; ?>
			</ul><!-- .organizations -->
		</li><!-- .section -->
		<?php
	}
	
	/**
	 * Outputs an individual position LI
	 * @uses the_loop
	 * @since 2.0.5
	 */
	function dragdrop_position() { ?>
		<li class="position" id="<?php the_ID(); ?>">
			<a href="<?php echo admin_url( 'post.php?post=' . get_the_ID() . '&action=edit' ); ?>">
				<?php echo the_title(); ?> 
			</a>
			<?php if ($date = $this->format_date( get_the_ID() ) ) echo "($date)"; ?>
		</li><!-- .position -->
	<?php
	}
	
	/**
	 * Creates the opening LI and UL for organizations
	 * @param object $organization the org
	 * @since 2.0.5
	 */
	function dragdrop_org_start( $organization ) { ?>
		<li class="organization" id="<?php echo $organization->term_id; ?>">
			<a href="<?php echo admin_url( 'edit-tags.php?action=edit&taxonomy=wp_resume_organization&tag_ID=' . $organization->term_id . '&post_type=wp_resume_position' ); ?>">
				<?php echo $organization->name; ?>
			</a>
			<ul class="positions">
		<?php
	}
	
	/**
	 * Closes the org's UL and LI
	 * @since 2.0.5
	 */
	function dragdrop_org_end( ) { ?>
			</ul><!-- .positions -->
		</li><!-- .organization -->
		<?php
	}
	
	/**
	 * Returns either the next or previous position's org
	 * @param int $delta either 1 or -1 for forward or backward
	 * @returns bool|object false if no org, org object if exists
	 * @since 2.0.5
	 */
	function get_delta_org( $delta ) {
		
		if ( empty( $this->query_obj->posts ) || !isset( $this->query_obj->current_post ) )
			return false;
		
		$post_key = $this->query_obj->current_post + $delta;
		
		if ( !isset( $this->query_obj->posts[ $post_key ] ) )
			return false;
		
		return $this->get_org( $this->query_obj->posts[ $post_key ]->ID ); 
	}
	
	/**
	 * Peaks forward in the loop if possible, and tries to get next position's org
	 * @returns bool|object either false or the org object
	 * @since 2.0.5
	 */
	function get_next_org( ) {
	
		return $this->get_delta_org( 1 );
		  
	}
	
	/**
	 * Peaks backward in the loop if possible, and tries to get previous position's org
	 * @returns bool|object either false or the org object
	 * @since 2.0.5
	 */
	function get_previous_org() {

		return $this->get_delta_org( -1 );
  
	}
	
	/**
	 * makes the plugin translation friendly	
	 * @since 2.0
	 */
	function i18n() {
		load_plugin_textdomain( 'wp-resume', null, dirname( plugin_basename( __FILE__ ) ) .'/languages/' );	
		
	}

	/**
	 * Checks DB version on admin init and upgrades if necessary
	 * Used b/c 1) no CPTs on activation hook, 2) no activation hook on multi-update
	 * @since 1.6
	 */
	function admin_init() {
			
		$options = $this->get_options();
		
		//check for upgrade and upgrade, works as an activation hook, more or less.
		if ( !isset($options['db_version']) || $options['db_version'] < $this->version )
			$options = $this->upgrade_db();

		register_setting( 'wp_resume_options', 'wp_resume_options', array( &$this, 'options_validate' ) );
		
		//If we are on the wp_resume_options page, enque the tinyMCE editor
		if ( !empty ($_GET['page'] ) && $_GET['page'] == 'wp_resume_options' ) {
			wp_enqueue_script('editor');
			add_thickbox();
			wp_enqueue_script('media-upload');
			wp_enqueue_script('post');
			
			//add css
			wp_enqueue_style('wp_resume_admin_stylesheet', plugins_url(  'css/admin-style.css', __FILE__ ) );	
		}
		
		
	}
	
	/**
	 * Tells WP to load our javascript files
	 */
	function enqueue_scripts() {
	
		$suffix = ( WP_DEBUG ) ? 'dev.' : '';
	
		$post = false;
		if ( !empty( $_GET['post'] ) )
			$post = get_post( $_GET['post'] );
	
		//load javascript with libraries on options page
		if ( !empty ( $_GET['page'] ) && $_GET['page'] == 'wp_resume_options' ) { 
			wp_enqueue_script( 'wp_resume', plugins_url('/js/wp_resume.' . $suffix . 'js', __FILE__), array("jquery", "jquery-ui-core", "jquery-ui-sortable", "wp-lists", "jquery-ui-sortable"), $this->version );		
		//if on the org, section, or edit page, load the script without all the libraries
		} else if ( ( !empty( $_GET['post_type'] ) && $_GET['post_type'] == 'wp_resume_position' ) ||
			 ( !empty( $_GET['post'] ) && $post && $post->post_type == 'wp_resume_position' ) ) { 
			wp_enqueue_script( 'wp_resume', plugins_url('/js/wp_resume.' . $suffix . 'js', __FILE__), array("jquery"), $this->version );		
		}
		
		$data = array( 
			'more' => __('More', 'wp-resume'),
			'less' => __('less', 'wp-resume'),
			'yes' => __('Yes!', 'wp-resume'),
			'no' => __('No.', 'wp-resume'),
			'hideAdv' => __('Hide Advanced Options', 'wp-resume'),
			'showAdv' => __('Show Advanced Options', 'wp-resume'),
			'orgName' => __('The name of the organization as you want it to appear', 'wp-resume'),
			'orgLoc' => __('Traditionally the location of the organization (optional)', 'wp-resume'),
			'missingTaxMsg' => __( 'Please make sure that the position is associated with a section before saving', 'wp-resume'),
		);
		wp_localize_script( 'wp_resume', 'wp_resume', $data );
	
	}

	/**
	 * Moves information around the database, supports back to 1.5
	 * @since 1.2
	 */
	function upgrade_db() {
		
		//default fields and values
		$fields['global'] = array('fix_ie' => true, 'rewrite' => false, 'hide-title' => false );
		$fields['user'] = array('name'=>'', 'summary' => '', 'contact_info'=> array(), 'order'=>array(), 'hide-donate' => false );
		$i = 0;	foreach ( $this->get_sections( false ) as $section)
				$fields['user']['order'][$section->term_id] = $i++;

		//get our options
		$options = $this->get_options();
		
		//check to see if we have any sections
		if ( sizeof( $this->get_sections(false) ) == 0 ) {
			//add the sections
			wp_insert_term( 'Education', 'wp_resume_section');
			wp_insert_term( 'Experience', 'wp_resume_section' );
			wp_insert_term( 'Awards', 'wp_resume_section' );
		}
			
		//add multi-user support (v. 1.6)
		if ( !isset( $options['db_version'] ) || substr( $options['db_version'], 0, 2 ) < '1.6' ) {
			
			$usermeta = array();
			$current_user = wp_get_current_user();
			
			//migrate $options[field] to (usermeta) [wp_resume][field] and kill original
			foreach ($fields['user'] as $field=>$value) {
				if ( isset( $options[$field] ) ) {
					$usermeta[$field] = $options[$field];
					unset($options[$field]);
				} 
			}

			//store usermeta to current user
			//(assumption: user upgrading is author of resume)
			update_user_meta($current_user->ID, 'wp_resume', $usermeta);

		}
		
		//if global fields are null, set to default
		foreach ( $fields['global'] as $key=>$value ) {
			if ( !isset( $options[$key] ) )
				$options[$key] = $value;
		}
		
		//if user fields are null for any user, set to default
		global $wpdb;
		$users = $wpdb->get_col( $wpdb->prepare("SELECT $wpdb->users.ID FROM $wpdb->users") );
		foreach ($users as $user) {
		
			if ( isset( $options['db_version'] ) && $options['db_version'] < '2.2' ) {
				//move from user_meta to user_option
				$user_options = get_user_meta( $user, 'wp_resume', true );
				delete_user_meta( $user, 'wp_resume' );
			} else {		
				//get current options
				$user_options = $this->get_user_options( (int) $user );
			}
			
			//loop default fields
			foreach ( $fields['user'] as $key=>$value ) {
			
				//check they exist, if not set
				if ( !isset( $user_options[$key] ) )
					$user_options[$key] = $value;

				//update
				$this->update_user_options( $user, $user_options );
			}	
			
		}
			
		//DB Versioning
		$options['db_version'] = $this->version;

		//store updated options
		$this->update_options( $options, false );
		
		//flush rewrite rules just in case
		flush_rewrite_rules();
		
		do_action('wp_resume_upgrade', $options['db_version'] );
	  
		return $options;

	}


	/**
	 * Modifies the add organization page to provide help text and better descriptions
	 * @since 1.2
	 * @disclaimer it's not pretty, but it get's the job done.
	 */
	function org_helptext() { ?>
		<noscript>
			<h4><?php _e('Help', 'wp-resume'); ?></h4>
			<p><strong><?php _e('Name', 'wp-resume'); ?></strong>: <?php _e('The name of the organization', 'wp-resume'); ?></p>
			<p><strong><?php _e('Parent', 'wp-resume'); ?></strong>: <?php _e('Do not add a parent', 'wp-resume'); ?></p>
			<p><strong><?php _e('Description', 'wp-resume'); ?></strong>: <?php _e('You can put the location of the organization here (optional)', 'wp-resume'); ?></p>
		</noscript>
	<?php }


	/**
	 * Includes resume template on shortcode use 
	 * @since 1.3
	 */
	function shortcode( $atts ) {
	
		//determine author and set as global so templates can read
		$this->author = $this->get_author( $atts );

		ob_start();
		do_action('wp_resume_shortcode_pre');

		if ( !( $resume = wp_cache_get( $this->author . '_resume', 'wp_resume' ) ) ) {
			$this->include_template('resume.php');
			$resume = ob_get_contents();
			wp_cache_set( $this->author . '_resume', $resume, 'wp_resume', $this->ttl );
		}
		
		do_action('wp_resume_shortcode_post');
		ob_end_clean();
		
		apply_filters('wp_resume_shortcode', $resume);

		return $resume;
	}

	/**
	 * Adds feed support to the resume 
	 * @since 1.5
	 */
	function add_feeds() {
	
		global $post;
		
		//feed 404
		if ( !$post )
			return false;
		
		if ( preg_match( '/\[wp_resume([^\]]*)]/i', $post->post_content ) === FALSE) 
			return;

		add_feed('text', array( &$this, 'plain_text' ) );
		add_feed('json', array( &$this, 'json' ) );
		
		add_action('wp_head', array( &$this, 'header' ) );
	}

	/**
	 * Adds HTML5 support to header
	 */
	function header() { 
	
		if ( !$this->resume_in_query() )
			return;
			
		if ( !is_single() )
			return;
			
		$options = $this->get_options(); ?>
			<link rel="profile" href="http://microformats.org/profile/hcard" />
			<?php if ($options['fix_ie']) { ?>
			<!--[if lt IE 9]>
				<script type="text/javascript" src="<?php echo plugins_url(  'js/html5.js', __FILE__ ); ?>"></script>
			<![endif]-->
			<?php } ?>
	<?php }

	/**
	 * Includes the plain text template
	 * @since 1.5
	 */
	function plain_text() {
		$this->feed_get_author();
		header('Content-Type: text/plain; charset='. get_bloginfo('charset') );
		$this->include_template('resume-text.php');
		do_action('wp_resume_plain_text');
	}

	/**
	 * Includes the json template
	 * @since 1.5
	 */
	function json() {
		$this->feed_get_author();
		header('Content-type: application/json; charset='. get_bloginfo('charset') );
		$this->include_template('resume-json.php');
		do_action('wp_resume_json');
	}

	/**
	 * Includes a wp_resume template file
	 * First looks in current theme directory for file, otherwise includes defaults
	 * @since 1.5
	 */
	function include_template( $template ) {
		
		//use WP's native function to search for child theme, then parent
		$file = locate_template( $template );
		
		//the theme has no template, use default
		if ( $file == '' )
			$file = dirname( __FILE__ ) . '/includes/' . $template;
			
		load_template( $file );
									
	}

	/**
	 * Fuzzy gets author for current resume page
	 * Looks at:
	 * 1) Attributes of shorcode (user="[username]")
	 * 2) Author of page that calls the shortcode
	 * 
	 * @param array $atts attributes passed from shortcode callback
	 * @since 1.6
	 */
	function get_author( $atts = array() ) {
		
		//if user is passed as an attribute, that's our author
		if ( isset( $atts['user'] ) ) 
			return $atts['user'];
		
		//otherwise grab the author from the post
		global $post;
		$user = get_userdata($post->post_author);
		return $user->user_nicename;
	}

	/**
	 * Injects resume rewrite rules into the rewrite array when applicable
	 */
	function rewrite_rules() {
		$options = $this->get_options();
		
		if ( !isset($options['rewrite']) || !$options['rewrite'] )
			return;

		global $wp_rewrite;
		$rw_structure = 'resume/%wp_resume_section%/%wp_resume_organization%/%wp_resume_position%/';
		add_rewrite_tag("%wp_resume_section%", '([^/]+)', "wp_resume_section=");
		add_rewrite_tag("%wp_resume_organization%", '([^/]+)', "wp_resume_organization=");
		add_rewrite_tag("%wp_resume_position%", '([^/]+)', "wp_resume_position=");
		$wp_rewrite->add_permastruct('wp_resume_position', $rw_structure);  

	}

	/**
	 * Generates permalink for a given resume position
	 */
	function permalink($link, $post, $leavename, $sample) {

		$options = $this->get_options();
		
		if ( $post->post_type != 'wp_resume_position' && isset($options['rewrite']) && $options['rewrite'] )
			return $link;
			
		$section = wp_get_post_terms($post->ID, 'wp_resume_section');
		$org = wp_get_post_terms($post->ID, 'wp_resume_organization');
		
		$rewritecode = array(
			  '%wp_resume_section%',
			  '%wp_resume_organization%',
		);
		
		$replace = array(
			( isset( $section[0]->slug) ) ? $section[0]->slug : null,
			( isset( $org[0]->slug) ) ? $org[0]->slug : null,
		);	
		
		$link = str_replace($rewritecode, $replace, $link);
		
		$link = apply_filters('wp_resume_permalink', $link);

		return $link;
	}

	/**
	 * Adds WPML support to wp resume sections
	 * @since 1.6
	 * @h/t rvencu 
	 */
	function exclude_the_terms($exclusions) {
		
		//check for WPML, if not, kick
		if ( !class_exists('SitePress') )
			return $exclusions;
			
		//if WPML exists,  change the $exclusions
		global $sitepress;
		$exclusions .= $sitepress->exclude_other_terms( '', array( 'taxonomy' => 'wp_resume_section' ) );

		return $exclusions;
	}

	/**
	 * Parses current author for feeds from shortcode
	 */
	function feed_get_author(){
		global $post;
		
		if ( preg_match( '/\[wp_resume user=\"([^\"]*)"]/i', $post->post_content, $matches ) == 0) {
			
			$user = get_userdata($post->post_author);
			$this->author = $user->user_nicename; 
			
		} else {
		
			$this->author = $matches[1];
		
		}
		
		$this->author = apply_filters( 'wp_resume_author', $this->author );
		
		return $this->author;
		
	}
	
	/**
	 * Allows non-admins to edit their own resume options
	 * @since 2.0.2
	 * @param string $cap the cap to check
	 * @return string edit_post casts
	 */
	function cap_filter( $cap ) {
		return 'edit_posts';
	}
	
	/** 
	 * Applies filter and returns author's name
	 * @uses $author
	 * @returns string the author's name
	 */
	function get_name() {
		$options = $this->get_user_options( );
		
		if ( !isset( $options['name'] ) )
			return;
		
		return apply_filters( 'resume_name', $options['name'] );
	}
	
	/**
	 * Returns the title of the postition, or if rewriting is enabled, a link to the position
	 * @param int $ID the position ID
	 * @return string the title, or the title link
	 */
	function get_title( $ID ) {
	
		$options = $this->get_options();
		
		if ( !isset( $options['rewrite'] ) || !$options['rewrite'] ) {
			$title = get_the_title();
		} else {
			$title = '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
			$title = apply_filters( 'resume_position_link', $title );
		}
		
		return apply_filters( 'resume_position_title', $title );
		 
	}
	
	/**
	 * Returns the section name, or a link to the section if rewrites are on
	 * @param object $section the section object
	 * @param bool $link whether to link if possible	
	 * @returns string the section name or link to section
	 */
	function get_section_name( $section, $link = true ) {
	
		return $this->get_taxonomy_name( $section, 'section', $link );
			
	}
	
	/**
	 * Returns the organization name, or a link to the organization if rewrites are on
	 * @param object $organization the organization object
	 * @param bool $link whether to link if possible
	 * @returns string the organization name or link to organization
	 */	
	function get_organization_name( $organization, $link = true ) {

		return $this->get_taxonomy_name( $organization, 'organization', $link );
		
	}
	
	/**
	 * Given a taxonomy object and taxonomy type, returns either the taxnomy name or a link to the taxnomy
	 * @param object $object the taxonomy object
	 * @param string $taxnonomy the taxnomy slug after "resume_"
	 * @param bool $link whether to link if possible
	 * @returns string the formatted taxonomy name/link
	 */
	function get_taxonomy_name( $object, $taxonomy, $link ) {
		global $post;
		
		$options = $this->get_options();
		
		if ( !$link )
			return apply_filters( "resume_{$taxonomy}_name", $object->name );
		
		//org link
		if ( $taxonomy == 'organization' && $this->get_org_link( $object->term_id ) ) {
			$link = $this->get_org_link( $object->term_id );
		
		//rewrite links
		} else if ( isset( $options['rewrite'] ) && $options['rewrite'] ) {
			$link = get_term_link( $object, "resume_{$taxonomy}" );
		
		//no link
		} else {
			return apply_filters( "resume_{$taxonomy}_name", $object->name );
		}

		$title = '<a href="' . $link . '">' . $object->name . '</a>';
		
		$title = apply_filters( "resume_{$taxonomy}_link", $title );
		$title = apply_filters( "resume_{$taxonomy}_name", $title );
		
		return $title;
	}
	
	/**
	 * Returns the author's contact info
	 * @uses $author
	 * @returns array of contact info
	 */
	function get_contact_info() {
	
		$options = $this->get_user_options( );
		
		if ( !isset( $options['contact_info'] ) || !is_array( $options['contact_info'] ) )
			return array();
		
		return apply_filters( 'resume_contact_info', $options['contact_info'] );	
		
	}
	
	/**
	 * Returns the resume summary, if any
	 * @uses $author
	 * @returns string the resume summary
	 */
	function get_summary() {

		$options = $this->get_user_options( );
		
		if ( empty( $options['summary'] ) )
			return array();
		
		return apply_filters( 'resume_summary', $options['summary'] );
		
	}
	
	/**
	 * Stores user's preference to hide the donate message via AJAX
	 */
	function hide_donate() {
		
		check_ajax_referer( 'wp_resume_hide_donate' , '_ajax_nonce-wp-resume-hide-donate' );
		
		$current_user_id = get_current_user_id();
		$options = $this->get_user_options( (int) $current_user_id );
		$options['hide-donate'] = true;
		
		$this->update_user_options( $current_user_id, $options);
		
		die( 1 );
		
	}
	
	/**
	 * Adds field to edit organization page to allow linking to organization's site
	 */
	function link_field( $term, $taxonomy = '' ) {

		$options = $this->get_options( ); 
		$tax = get_taxonomy( $taxonomy );
	
		$edit = ( $taxonomy != '' );
		$value = '';
		if ( $edit && $this->get_org_link( $term->term_id ) )
			$value = $this->get_org_link( $term->term_id );

		if ( $edit ) { ?>
			<tr class="form-row">
				<th scope="row" valign="top">
					<label for="link">Link</label>
				</th>
				<td class="form-field">
		<?php } else { ?> 
			<div class="form-field">
				<label for="link">Link</label>			
		<?php } ?>
			<?php wp_nonce_field( 'wp_resume_org_link', 'wp_resume_nonce' ); ?>
			<input type="text" name="org_link" value="<?php echo esc_attr( $value ); ?>" <?php if ( $edit ) { echo 'size="40"'; } ?> />
			<p class="description">(optional) The link to the organization's home page</p>
		<?php echo ( $taxonomy == '' ) ? '</div' : '</td></tr>'; ?>
	<?php
	}
	
	/**
	 * Saves organization link
	 */
	function save_link_field( $termID ) {
		
		wp_verify_nonce( 'wp_resume_org_link', $_REQUEST['wp_resume_nonce'] );
	
		$tax = get_taxonomy( 'wp_resume_organization' );	
				
		if ( !current_user_can( $tax->cap->edit_terms ) )
			return;
						
		$this->set_org_link( $termID, $_REQUEST['org_link'] );
				
	}
	
	/**
	 * Filters HTML from contact info array recursively
	 * @uses plaintext_contact_info_walker
	 */
	function plaintext_contact_info( $author = null ) {
		$author = $this->get_author( $author );
		$author_options = $this->get_user_options( $author );
		$contact_info = $author_options['contact_info'];
		
		array_walk_recursive( &$contact_info, array( &$this, 'plaintext_contact_info_walker' ) );
		
		$contact_info = apply_filters( 'resume_plaintext_contact_info', $contact_info );
				
		return $contact_info;
	
	}
	
	/**
	 * Helper function to parse contact info array from HTML to plaintext
	 */
	function plaintext_contact_info_walker( &$info ) {
		$info = wp_filter_nohtml_kses( $info );
	}
	
	/**
	 * Converts LIs to bullets
	 * @param string $text the HTML formatted text
	 * @return string plaintext with bullets
	 * @uses resume_plaintext_bullet
	 */
	function bulletit( $text ) {
		$bullet = apply_filters( 'resume_plaintext_bullet', '&bull; ' ); 
		return preg_replace( "#<li[^>]*>#", $bullet, $text );
	
	}
	
	/**
	 * Wraps date in parenthesis where appropriate
	 * @param string $date the date
	 * @return string the formatted date
	 */
	function format_date_plaintext( $date ) {

		if ( strlen( trim ( $date ) ) > 0 )
			return " ($date)";
	
		return $date;
		
	}
	
	/**
	 * Converts HTML entities, and passes proper charset
	 * @param strint $text the text
	 * @return string plaintext
	 */
	function html_entity_decode( $text ) {
		return html_entity_decode( $text, null, get_bloginfo('charset') );
	}
	
	/**
	 * Prepends dash to location when appropriate
	 * @param string $location the location
	 * @return string the formatted location
	 */
	function format_plaintext_location( $location ) {
	
		if ( strlen( trim( $location ) ) == 0 )
			return '';
			
		return " &ndash; $location";
	
	}
	
	
}

$wp_resume = new WP_Resume();