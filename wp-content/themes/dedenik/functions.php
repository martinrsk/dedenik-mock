<?php
/** Theme bootstrap. Search schema and initial indexing require an explicit operation. */
defined( 'ABSPATH' ) || exit;
require get_template_directory() . '/inc/setup.php';
require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/world-admin.php';
require get_template_directory() . '/inc/footer.php';
require get_template_directory() . '/inc/ai-search.php';
require get_template_directory() . '/inc/search-index.php';
require get_template_directory() . '/inc/discussion-index.php';
require get_template_directory() . '/inc/reader-index.php';
require get_template_directory() . '/inc/discussants.php';
require get_template_directory() . '/inc/category-posts.php';
