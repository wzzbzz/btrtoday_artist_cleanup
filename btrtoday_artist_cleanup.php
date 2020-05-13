<?php
/*
Plugin Name: BTRToday ArtistCleanup
Plugin URI:  http://jimwilliamsconsulting.com/wordpress/plugins/btrtoday_analytics
Description: Tool For Cleaning Artist Database 
Version:     1
Author:      Jim Williams
Author URI:  http://www.jimwilliamsconsulting.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: dontknow
Domain Path: /languages
*/

class BTRtoday_ArtistCleanup{
    
	const default_page_slug = "btrtoday_artist_cleanup";
	
	private $current_page;	
	private $series;
    private $start;
    private $end;
	
	public function __construct(){
		$this->hooks();
		$this->register_routes();
		
	}
	
	public function hooks(){

		/* btr daily dashboard */
		#add_action( 'wp_dashboard_setup', array( $this,'add_daily_podcast_downloads_meta_box' ));
		//add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        //add_action( 'admin_enqueue_scripts', array( $this,'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this,'create_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'init' ) );
		
        // default time-range = previous 7 days for starters.
		
	}
    
    
    public function admin_init(){
		
        $this->create_menu();
		
    }
	
	public function init(){
		$this->register_taxonomies();
	}

	
	public function create_menu(){
		
		add_submenu_page ( "tools.php", "Artist Cleanup", "Artist Cleanup", "manage_options", "btrtoday_artist_cleanup", array($this,"render_artist_cleanup") );
    
	}
	
	
	
	public function render_artist_cleanup(){
		?>
		<div class="wrap">
		<h3>Artist Cleanup</h3>
		<?php
		echo "<br><br>";
		global $wpdb;
		
		//$term = get_term(54125);
		//$this->move_term($term, 'artist');

		//$this->fix_spaces();
		//$this->fix_c2ad();
		//$this->fix_hyphens();
		//$this->fix_duplicates();
		$this->new_method();
		$this->fix_commas();
		$this->fix_features();
		?>
		</div>
		<?php
		return;
		
	}
	
	private function new_method(){
		set_time_limit(0);
		global $wpdb;
		$sql = "SELECT t.*, count(tr.object_id) as post_count
					FROM wp_term_relationships tr
					JOIN wp_term_taxonomy tt
						ON tt.term_taxonomy_id=tr.term_taxonomy_id
					JOIN wp_terms t
						ON t.term_id = tt.term_id
					WHERE tt.taxonomy='artist'
						GROUP BY t.term_id
						ORDER BY post_count DESC LIMIT 100;";
		$artists = $wpdb->get_results($sql);
		
		foreach($artists as $artist){
		?>
			<hr>checking<?=$artist->name;?><br>
		<?php 
			$this->find_and_merge($artist);
		}
	}
	
	private function find_and_merge( $artist ){
		global $wpdb;
		// first go by slug
		$artists = $this->artists_by_pattern('%'.$artist->slug.'%', 'slug', " AND t.term_id NOT IN('".$artist->term_id."')");
		?>
		<ul>
		<?php
		foreach($artists as $artist){
		?>
			<li><?=$artist->name;?></li>;
		<?php
		}
		?>
		</ul>
		<?php
		return;
		
	}
	
	private function artists_by_pattern($pattern, $field='name', $exclude=''){
		
		global $wpdb;
		$sql = "SELECT * from wp_terms t join wp_term_taxonomy tt on t.term_id = tt.term_id WHERE tt.taxonomy='artist' and t.$field LIKE '$pattern' $exclude";
		$results = $wpdb->get_results($sql);
		
		return $results;
	}
	
	/* Some of the artists come in with spaces in front of their names. */
	public function fix_spaces(){
		echo "<br>fixing spaces<br>";
		$artists = $this->artists_by_pattern(' %');
		foreach($artists as $artist){
			echo "<Br>checking {$artist->name}<br>";
			// get the real name by stripping the spaces
			preg_match("/^\s+(.*)/" , $artist->name , $matches );
			
			if(count($matches)){
				
				$name = $matches[1];
				// look for an existing artist
				$term = get_term_by("name", $name, "artist");
				
				//we have one.  replace the post.
				if(!empty($term)){
					$this->merge_terms( $artist , $term );
					$this->move_term( $artist );
					
				}
				else{
					echo "updating {$artist->term_id} {$artist->name}<br>";
					wp_update_term($artist->term_id, 'artist', array('name'=>$name));
				}
			}
			
			else if($match = strpos($artist->name, chr(194).chr(160)) !==FALSE){
			
				$name = str_replace(chr(194).chr(160),"",$artist->name);
				
				// look for an existing artist
				$term = get_term_by("name", $name, "artist");
				//we have one.  replace the post.
				if(!empty($term)){
					$this->merge_terms( $artist , $term );
					$this->move_term( $artist );
				}
				else{
					echo "updating {$artist->term_id} {$artist->name}<br>";
					wp_update_term($artist->term_id, 'artist', array('name'=>$name));
				}
				
			}
			
		}
		
	}
	
	public function fix_c2ad(){
		echo "<br>fixing %c2%ad<br>";
		$artists = $this->artists_by_pattern('%\%c2\%ad%', 'slug');
		foreach( $artists as $artist ){
			
			$slug = str_replace( '%c2%ad' , '' , $artist->slug );
			
			if($artist->slug!==$slug){
				// look for an existing artist
				$term = get_term_by("slug", $slug, "artist");
				//we have one.  replace the post.
				if(!empty($term)){
					$this->merge_terms( $artist , $term );
					$this->move_term( $artist );
				}
				else{
					echo "updating {$artist->term_id} {$artist->name}<br>";
					wp_update_term($artist->term_id, 'artist', array('slug'=>$slug));
				}
				
			}
			else{
				die("3");	
			}
		}
		
	}	

	public function fix_hyphens(){
		$artists = $this->artists_by_pattern("-%");
		
		foreach( $artists as $artist ){
			
			$name = trim(str_replace("-","",$artist->name));
			if(empty($name)){
				continue;
			}
			
			$term = get_term_by("name", $name, 'artist');
			
			if(!empty($term)){
				$this->merge_terms( $artist , $term );
				$this->move_term( $artist );
			}
			else{
				echo "updating {$artist->term_id} {$artist->name}<br>";
				wp_update_term($artist->term_id, 'artist', array('name'=>$name));
			}
			
		}
	}
	
	public function fix_duplicates(){
		
		for($i=2;$i<15;$i++){
			$artists = $this->artists_by_pattern('%-'.$i, 'slug' );	
			foreach($artists as $artist){
				
				echo "checking {$artist->name} {$artist->slug}<br>";
				
				if(sanitize_title($artist->name) == $artist->slug){
					echo "seems to be ok.<br><br>";
					continue;
				}
				preg_match('/(.*)\-[0-9]+/',$artist->slug,$matches);
				
				$term = get_term_by( 'slug' , $matches[1] , 'artist' );
				if(!empty($term)){

					if($artist->count > $term->count){
						echo "merging {$term->name} into {$artist->name}<br>";
						$this->merge_terms( $term, $artist );
						$this->move_term( $term );
						wp_update_term($artist->term_id, 'artist', array('slug'=>$term->slug));
					}
					else{
						echo "merging {$artist->name} into {$term->name}<br>";
						$this->merge_terms( $artist , $term );
						$this->move_term( $artist );
					}
					
				}
				else{
					echo "no duplicate found<br>";
				}
			}
			
		}
	}
	
	public function fix_commas(){
		$terms = $this->artists_by_pattern('%,%');
		
		foreach($terms as $term){
			
			$artists = $this->maybe_get_artists_from_name($term->name);
			
			$perfect = true;
			foreach($artists as $artist_name){
				echo "checking $artist_name : ";
				$artist = get_term_by("name" , $artist_name , 'artist' );
				if(!empty($artist)){
					$this->merge_terms($term, $artist);
				}
				else{
					echo $artist_name." not found<br>";
					$perfect = false;
				}
			}
			if($perfect){
				echo "found all artists!  Deleting<br>";
				$this->move_term( $term, 'collaboration' );
				
			}
			else{
				echo "Not all artists found.  Leaving for human analysis.";
				$this->move_term( $term );
			}
			
		}
	}
	
	private function maybe_get_artists_from_name($name){
		
		echo "checking $name<br>";
		echo "first removing probable locations<br>";
		$regex = "/(.*)(\/)([a-zA-Z]+,?\s?[a-zA-Z]+)/";
		preg_match($regex, $name, $matches);
		
		if(count($matches)){
			// check to see if the match is an artist
			$term = get_term_by("name",$matches[3],'artist');
			if(empty($term))
				$name = str_replace("/".$matches[3], "", $name);
				
		}
		
		$regex = "/(\s?,\s?|\s?ft\.\s?|\s?feat\.\s?|\s?&amp;\s?)/";
		$parts = preg_split($regex,$name);
		
		$artists = [];
		foreach($parts as $part){
			preg_match('/(.*)\s\((.*)\)/',$part,$matches);
			if(count($matches)){
				$artists[] = $matches[1];
			}	
			else{
				$artists[] = $part;
			}
		}

		return $artists;
	}
	
	public function fix_features(){}
	public function register_routes(){}

	public function register_taxonomies(){
		
		register_taxonomy('maybe-artist',
            array('listen','tv','read'),
              array(
                'labels' => array(
                  'name'=>__('Artists, Maybe'),
                  'all_items'=> __( 'All Questionable Artists' ),
                  'edit_item'=> __( 'Edit Questionable Artists' ),
                  'add_new_item'=> __('Add New '),
                  'update_item'=>__('Update Questionable Artist'),
                  'separate_items_with_commas' => __(''),
                  
                ),
                'hierarchical'=>false,
                'meta_box_cb' => false,
              )
        );
		
		register_taxonomy('collaboration',
            array('listen','tv','read'),
              array(
                'labels' => array(
                  'name'=>__('Collaborations'),
                  'all_items'=> __( 'All Collaborations' ),
                  'edit_item'=> __( 'Edit Collaborations' ),
                  'add_new_item'=> __('Add New '),
                  'update_item'=>__('Update Collaborations'),
                  'separate_items_with_commas' => __(''),
                  
                ),
                'hierarchical'=>false,
                'meta_box_cb' => false,
              )
          );
		
	}
	private function merge_terms($err, $fix){
		echo "Merging {$err->name} posts into {$fix->name}<br>";
		global $wpdb;
		$sql = "SELECT *
		FROM wp_term_relationships tr
			JOIN
				wp_term_taxonomy tt on tr.term_taxonomy_id=tt.term_taxonomy_id
			JOIN
				wp_terms t on t.term_id = tt.term_id
		WHERE
			t.term_id = '{$err->term_id}'";
		
		$results = $wpdb->get_results($sql);
		
		if(is_array($results)){
			foreach($results as $result){
				$this->add_post_term($fix, $result->object_id);
				//$this->remove_post_term($err, $result->object_id);
			}
		}

	}
	
	private function move_term( $term, $taxonomy='maybe-artist' ){
		global $wpdb;
		echo "Moving {$term->name} {$term->term_id} to $taxonomy<br>";
		$sql = "UPDATE wp_term_taxonomy SET taxonomy='$taxonomy' WHERE term_id='{$term->term_id}'";
		$wpdb->query($sql);
	}
	
	private function remove_post_term($term, $post_id){
		echo "Removing {$term->name} from $post_id<br>";
		$artists = wp_get_post_terms($post_id, 'artist', array('fields'=>'ids'));
		
		$idx = array_search( $term->term_id , $artists );
		unset($artists[$idx]);
		wp_set_post_terms($post_id, 'artist', $artists);
	}
	
	private function add_post_term($term, $post_id){
		
		wp_set_post_terms( $post_id, array( $term->term_id) , 'artist' , true );
	}
	
}


$btr_artist_cleanup = new BTRtoday_ArtistCleanup();
