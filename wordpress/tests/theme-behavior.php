<?php
// Offline behavior checks. These do not substitute for WordPress integration tests.
define('ABSPATH', __DIR__);
$mods = array(); $options = array(); $assigned = false; $protected = false; $thumbnail = true; $terms = array();
function absint($value) { return abs((int)$value); }
function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function __($value,$domain) { return $value; }
function esc_html__($value,$domain) { return $value; }
function has_nav_menu($location) { return $GLOBALS['assigned']; }
function get_option($key,$default=false) { return $GLOBALS['options'][$key] ?? $default; }
function get_theme_mod($key,$default=false) { return $GLOBALS['mods'][$key] ?? $default; }
function wp_get_nav_menu_object($id) { return $id === 7 ? (object)array('term_id'=>7) : false; }
function get_permalink($id) { return 'https://example.test/page/'.$id.'/'; }
function home_url($path) { return 'https://example.test'.$path; }
function get_theme_file_uri($path) { return 'https://example.test/wp-content/themes/dedenik/'.$path; }
function get_the_category() { return $GLOBALS['terms']; }
function get_post_meta($id,$key,$single) { return $GLOBALS['primary'] ?? 0; }
function get_the_ID() { return 42; }
function get_category_link($term) { return 'https://example.test/category/'.$term->term_id.'/'; }
function is_wp_error($value) { return $value instanceof Exception; }
function has_post_thumbnail() { return $GLOBALS['thumbnail']; }
function post_password_required() { return $GLOBALS['protected']; }
function the_post_thumbnail($size,$args) { echo '<img src="protected-source.jpg">'; }
function get_term($id,$taxonomy) { return $id===9 ? (object)array('term_id'=>9,'name'=>'Chatník') : new Exception('invalid'); }
function get_term_by($field,$value,$taxonomy) { return $GLOBALS['fixture_terms'][$taxonomy][$value] ?? $GLOBALS['fixture_categories'][$value] ?? false; }
function get_post($id) { return $GLOBALS['fixture_pages'][$id] ?? null; }
$theme_dir = dirname(__DIR__, 2) . '/wp-content/themes/dedenik';
require $theme_dir . '/inc/template-tags.php';
$tests=0;
function check($condition,$message) { $GLOBALS['tests']++; if (!$condition) { throw new RuntimeException($message); } }
$options['theme_mods_frontier']=array('nav_menu_locations'=>array('frontier-menu-primary'=>7));
check(dedenik_menu_args('frontier-menu-primary')['menu']===7,'Frontier fallback preserves menu');
$assigned=true; check(!isset(dedenik_menu_args('frontier-menu-primary')['menu']),'New assignment must take precedence');
$assigned=false; check(!isset(dedenik_menu_args('footer')['menu']),'Footer must not borrow primary menu');
$options['theme_mods_frontier']['nav_menu_locations']['frontier-menu-primary']=999;
check(!isset(dedenik_menu_args('frontier-menu-primary')['menu']),'Deleted old menu must not be forced');
check(dedenik_posts_url()==='https://example.test/','Latest-posts homepage URL');
$options['page_for_posts']=123; check(dedenik_posts_url()==='https://example.test/page/123/','Configured posts-page URL');
$terms=array((object)array('term_id'=>1,'name'=>'Fallback'),(object)array('term_id'=>2,'name'=>'<script>'));
$primary=2; ob_start(); dedenik_primary_category(); $html=ob_get_clean();
check(strpos($html,'category/2/')!==false,'Primary category honored');
check(strpos($html,'<script>')===false && strpos($html,'&lt;script&gt;')!==false,'Category names escaped');
$primary=99; ob_start(); dedenik_primary_category(); $html=ob_get_clean(); check(strpos($html,'category/1/')!==false,'Invalid primary category falls back');
$protected=true; ob_start(); dedenik_card_image(); $html=ob_get_clean(); check(strpos($html,'protected-source')===false,'Password-protected card must not disclose thumbnail');
ob_start(); require $theme_dir . '/comments.php'; $html=ob_get_clean(); check($html==='','Protected post must not render comments or form');
$protected=false; ob_start(); dedenik_card_image(); $html=ob_get_clean(); check(strpos($html,'protected-source')!==false,'Public thumbnail rendered');
$thumbnail=false; ob_start(); dedenik_card_image(); $html=ob_get_clean(); check(strpos($html,'card-placeholder')!==false,'Missing image has fallback');
$mods['dedenik_chat_category']=999; check(dedenik_chat_category()===false,'Invalid Chatnik category hides component');
$mods['dedenik_chat_category']=9; check(dedenik_chat_category()->term_id===9,'Configured Chatnik category used');
$fixture_pages=array(); ob_start(); require $theme_dir . '/template-parts/world.php'; $html=ob_get_clean(); check(strpos($html,'https://alzbetaadrak.cz/')!==false,'Default books card links to Alzbeta a drak');
$mods['dedenik_world_books']=3; $fixture_pages[3]=(object)array('post_type'=>'page','post_status'=>'draft','post_password'=>'');
ob_start(); require $theme_dir . '/template-parts/world.php'; $html=ob_get_clean(); check(strpos($html,'world-card-books')===false,'Draft promotional page must not leak');
$fixture_pages[3]->post_status='publish'; $fixture_pages[3]->post_password='fixture';
ob_start(); require $theme_dir . '/template-parts/world.php'; $html=ob_get_clean(); check(strpos($html,'world-card-books')===false,'Protected promotional page must not leak');
check(strpos($html,'https://open.spotify.com/show/4xeerT4CEz6vBbsb9YzGWM')!==false,'Podcast card defaults to the supplied Spotify show');
$mods['dedenik_world_podcast_url']='https://example.test/custom-podcast';
$items=dedenik_world_items(); check($items[0]['url']==='https://example.test/custom-podcast','Configured podcast link takes priority');
// The homepage category selection must keep its editorial order and real term URLs.
function esc_html_e($value,$domain) { echo esc_html($value); }
function get_template_part($slug) {}
$category_names = array('BTW','Člověčiny','Matyldoviny','Mlsotník','Rozcestník','Rozhledník','Chatník','Fotočlánky','Povídky','Literární léto','Kočky','Psi','Koně','Netopýři','Akvaristika','Dedivadlo');
$category_slugs = array('btw','cloveciny','matyldoviny','mlsotnik','rozcestnik','rozhlednik','chatnik','fotoclanek','povidky','literarni-leto','kocky','psi','kone','netopyri','akvaristika','dedivadlo');
$fixture_categories = array();
foreach ($category_slugs as $index => $slug) { $fixture_categories[$slug] = (object)array('term_id'=>500+$index); }
ob_start(); require $theme_dir . '/template-parts/discover.php'; $html=ob_get_clean();
preg_match_all('/<span>([^<]+)<\/span>/', $html, $labels);
check($labels[1] === $category_names, 'Homepage has exactly the reference categories in editorial order');
foreach ($category_slugs as $index => $slug) { check(strpos($html, 'href="https://example.test/category/'.(500+$index).'/"') !== false, 'Category tile uses its resolved term URL: '.$slug); }
$fixture_categories['fotoclanek'] = new Exception('missing');
unset($fixture_categories['kone']);
ob_start(); require $theme_dir . '/template-parts/discover.php'; $html=ob_get_clean();
check(substr_count($html, 'aria-disabled="true"') === 2, 'Unavailable categories stay visible without fabricated links');
check(strpos($html, 'category/507/') === false && strpos($html, 'category/512/') === false, 'Unavailable categories do not link to stale targets');
// Featured authors resolve editorial tag identities, not publishing accounts.
function get_term_link($term) { return 'https://example.test/tag/'.$term->slug.'/'; }
function wp_html_excerpt($value,$length,$more) { preg_match('/^./us',$value,$first); return $first[0] ?? ''; }
$author_names = array('Dede','Matylda','EvaB','Netopýrka','Tora','Pů','Yga','Alex','MartinR','Neviathiel','Aries','a další');
$author_slugs = array('dagmar-ruscakova','matylda','evab','netopyrka','tora','pu','yga','alex','martinr','neviathiel','aloisie-trpkosova-aries');
foreach ($author_slugs as $slug) { $fixture_terms['post_tag'][$slug] = (object)array('slug'=>$slug); }
ob_start(); require $theme_dir . '/template-parts/authors.php'; $html=ob_get_clean();
preg_match_all('/<span>([^<]+)<\/span><\/a>/', $html, $labels);
check($labels[1] === $author_names, 'Featured authors preserve all eleven names, editorial order and final more link');
check(strpos($html, 'href="https://example.test/tag/dagmar-ruscakova/"') !== false && strpos($html, 'href="https://example.test/tag/aloisie-trpkosova-aries/"') !== false, 'Dede and Aries resolve their actual author tags');
check(strpos($html, 'https://www.dedenik.cz/#f2-tagcloud-2') !== false, 'More authors links to the complete author list');
$fixture_terms['post_tag']['neviathiel'] = new Exception('missing');
ob_start(); require $theme_dir . '/template-parts/authors.php'; $html=ob_get_clean();
check(strpos($html, 'aria-disabled="true"') !== false && strpos($html, 'href="https://example.test/tag/neviathiel/"') === false, 'Missing author tag is visible without a fabricated archive link');
echo 'Passed '.$tests." offline behavior checks\n";
