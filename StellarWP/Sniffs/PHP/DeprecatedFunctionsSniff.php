<?php
namespace TEC\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP;

/**
 * This is a shameless copy of the work done by Squizlabs, specifically
 * Greg Sherwood <gsherwood@squiz.net> and Marc McIntyre <mmcintyre@squiz.net>,
 * but modified to match The Events Calendar standards.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @author    Zachary Tirrell <zbtirrell@gmail.com>
 * @copyright 2012 The Events Calendar
 * @license   https://github.com/the-events-calendar/coding-standards/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Discourages the use of deprecated WordPress functions that are kept in PHP for
 * compatibility with older versions.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Matthew Batchelder <borkweb@gmail.com>
 * @author    Zachary Tirrell <zbtirrell@gmail.com>
 * @copyright 2012 The Events Calendar
 * @license   https://github.com/the-events-calendar/coding-standards/blob/master/licence.txt BSD Licence
 * @version   Release: 1.4.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class DeprecatedFunctionsSniff extends PHP\ForbiddenFunctionsSniff
{

    /**
     * A list of forbidden functions with their alternatives.
     *
     * The value is NULL if no alternative exists. IE, the
     * function should just not be used.
     *
     * @var array(string => string|null)
     */
		public $forbiddenFunctions = array(
			"get_postdata"                          => "get_post()",
			"start_wp"                              => "The Loop - {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}",
			"the_category_ID"                       => "get_the_category()",
			"the_category_head"                     => "get_the_category_by_ID()",
			"previous_post"                         => "previous_post_link()",
			"next_post"                             => "next_post_link()",
			"user_can_create_post"                  => "current_user_can()",
			"user_can_create_draft"                 => "current_user_can()",
			"user_can_edit_post"                    => "current_user_can()",
			"user_can_delete_post"                  => "current_user_can()",
			"user_can_set_post_date"                => "current_user_can()",
			"user_can_edit_post_date"               => "current_user_can()",
			"user_can_edit_post_comments"           => "current_user_can()",
			"user_can_delete_post_comments"         => "current_user_can()",
			"user_can_edit_user"                    => "current_user_can()",
			"get_linksbyname"                       => "get_bookmarks()",
			"wp_get_linksbyname"                    => "wp_list_bookmarks()",
			"get_linkobjectsbyname"                 => "get_bookmarks()",
			"get_linkobjects"                       => "get_bookmarks()",
			"get_linksbyname_withrating"            => "get_bookmarks()",
			"get_links_withrating"                  => "get_bookmarks()",
			"get_autotoggle"                        => null,
			"list_cats"                             => "wp_list_categories()",
			"wp_list_cats"                          => "wp_list_categories()",
			"dropdown_cats"                         => "wp_dropdown_categories()",
			"list_authors"                          => "wp_list_authors()",
			"wp_get_post_cats"                      => "wp_get_post_categories()",
			"wp_set_post_cats"                      => "wp_set_post_categories()",
			"get_archives"                          => "wp_get_archives()",
			"get_author_link"                       => "get_author_posts_url()",
			"link_pages"                            => "wp_link_pages()",
			"get_settings"                          => "get_option()",
			"permalink_link"                        => "the_permalink()",
			"permalink_single_rss"                  => "the_permalink_rss()",
			"wp_get_links"                          => "wp_list_bookmarks()",
			"get_links"                             => "get_bookmarks()",
			"get_links_list"                        => "wp_list_bookmarks()",
			"links_popup_script"                    => null,
			"get_linkrating"                        => "sanitize_bookmark_field()",
			"get_linkcatname"                       => "get_category()",
			"comments_rss_link"                     => "post_comments_feed_link()",
			"get_category_rss_link"                 => "get_category_feed_link()",
			"get_author_rss_link"                   => "get_author_feed_link()",
			"comments_rss"                          => "get_post_comments_feed_link()",
			"create_user"                           => "wp_create_user()",
			"gzip_compression"                      => null,
			"get_commentdata"                       => "get_comment()",
			"get_catname"                           => "get_cat_name()",
			"get_category_children"                 => "get_term_children()",
			"get_the_author_description"            => "get_the_author_meta('description')",
			"the_author_description"                => "the_author_meta('description')",
			"get_the_author_login"                  => "get_the_author_meta('login')",
			"the_author_login"                      => "the_author_meta('login')",
			"get_the_author_firstname"              => "get_the_author_meta('first_name')",
			"the_author_firstname"                  => "the_author_meta('first_name')",
			"get_the_author_lastname"               => "get_the_author_meta('last_name')",
			"the_author_lastname"                   => "the_author_meta('last_name')",
			"get_the_author_nickname"               => "get_the_author_meta('nickname')",
			"the_author_nickname"                   => "the_author_meta('nickname')",
			"get_the_author_email"                  => "get_the_author_meta('email')",
			"the_author_email"                      => "the_author_meta('email')",
			"get_the_author_icq"                    => "get_the_author_meta('icq')",
			"the_author_icq"                        => "the_author_meta('icq')",
			"get_the_author_yim"                    => "get_the_author_meta('yim')",
			"the_author_yim"                        => "the_author_meta('yim')",
			"get_the_author_msn"                    => "get_the_author_meta('msn')",
			"the_author_msn"                        => "the_author_meta('msn')",
			"get_the_author_aim"                    => "get_the_author_meta('aim')",
			"the_author_aim"                        => "the_author_meta('aim')",
			"get_author_name"                       => "get_the_author_meta('display_name')",
			"get_the_author_url"                    => "get_the_author_meta('url')",
			"the_author_url"                        => "the_author_meta('url')",
			"get_the_author_ID"                     => "get_the_author_meta('ID')",
			"the_author_ID"                         => "the_author_meta('ID')",
			"the_content_rss"                       => "the_content_feed()",
			"make_url_footnote"                     => null,
			"_c"                                    => "_x()",
			"translate_with_context"                => "_x()",
			"_nc"                                   => "_nx()",
			"__ngettext"                            => "_n()",
			"__ngettext_noop"                       => "_n_noop()",
			"get_alloptions"                        => "wp_load_alloptions())",
			"get_the_attachment_link"               => "wp_get_attachment_link()",
			"get_attachment_icon_src"               => "wp_get_attachment_image_src()",
			"get_attachment_icon"                   => "wp_get_attachment_image()",
			"get_attachment_innerHTML"              => "wp_get_attachment_image()",
			"get_link"                              => "get_bookmark()",
			"sanitize_url"                          => "esc_url_raw()",
			"clean_url"                             => "esc_url()",
			"js_escape"                             => "esc_js()",
			"wp_specialchars"                       => "esc_html()",
			"attribute_escape"                      => "esc_attr()",
			"register_sidebar_widget"               => "wp_register_sidebar_widget()",
			"unregister_sidebar_widget"             => "wp_unregister_sidebar_widget()",
			"register_widget_control"               => "wp_register_widget_control()",
			"unregister_widget_control"             => "wp_unregister_widget_control()",
			"delete_usermeta"                       => "delete_user_meta()",
			"get_usermeta"                          => "get_user_meta()",
			"update_usermeta"                       => "update_user_meta()",
			"get_users_of_blog"                     => null,
			"automatic_feed_links"                  => "add_theme_support( 'automatic-feed-links' )",
			"get_profile"                           => "get_the_author_meta()",
			"get_usernumposts"                      => "count_user_posts()",
			"funky_javascript_callback"             => null,
			"funky_javascript_fix"                  => null,
			"is_taxonomy"                           => "taxonomy_exists()",
			"is_term"                               => "term_exists()",
			"is_plugin_page"                        => "global \$plugin_page and/or get_plugin_page_hookname() hooks.",
			"update_category_cache"                 => null,
			"wp_timezone_supported"                 => null,
			"the_editor"                            => "wp_editor()",
			"get_user_metavalues"                   => null,
			"sanitize_user_object"                  => null,
			"get_boundary_post_rel_link"            => null,
			"start_post_rel_link"                   => null,
			"get_index_rel_link"                    => null,
			"index_rel_link"                        => null,
			"get_parent_post_rel_link"              => null,
			"parent_post_rel_link"                  => null,
			"wp_admin_bar_dashboard_view_site_menu" => null,
			"is_blog_user"                          => "is_user_member_of_blog()",
			"debug_fopen"                           => "error_log()",
			"debug_fwrite"                          => "error_log() instead.",
			"debug_fclose"                          => "error_log()",
			"get_themes"                            => "wp_get_themes()",
			"get_theme"                             => "wp_get_theme()",
			"get_current_theme"                     => "(string) wp_get_theme()",
			"clean_pre"                             => null,
			"add_custom_image_header"               => "add_theme_support('custom-header', \$args)",
			"remove_custom_image_header"            => "remove_theme_support('custom-header')",
			"add_custom_background"                 => "add_theme_support('custom-background, \$args)",
			"remove_custom_background"              => null,
			"get_theme_data"                        => "wp_get_theme()",
			"update_page_cache"                     => null,
			"clean_page_cache"                      => null,
			"wp_explain_nonce"                      => "wp_nonce_ays()",
			"sticky_class"                          => "post_class()",
			"_get_post_ancestors"                   => null,
			"wp_load_image"                         => null,
			"image_resize"                          => null,
			"wp_get_single_post"                    => null,
			"user_pass_ok"                          => "wp_authenticate()",
			"_save_post_hook"                       => null,
			"gd_edit_image_support"                 => null,
		);


    /**
     * Constructor.
     *
     * Uses the Reflection API to get a list of deprecated functions.
     */
    public function __construct()
    {
    }//end __construct()


    /**
     * Generates the error or wanrning for this sniff.
     *
     * @param File   $phpcsFile The file being scanned.
     * @param int    $stackPtr  The position of the forbidden function
     *                          in the token array.
     * @param string $function  The name of the forbidden function.
     * @param string $pattern   The pattern used for the match.
     *
     * @return void
     */
    protected function addError($phpcsFile, $stackPtr, $function, $pattern=null)
    {
        $data  = array($function);
				$error = 'WordPress Function %s() has been deprecated.';

				if ( $this->forbiddenFunctions[ $function ] ) {
					$error .= ' Use ' . $this->forbiddenFunctions[ $function ] . ' instead.';
				}//end if

        $type  = 'Deprecated';

        if ($this->error === true) {
            $phpcsFile->addError($error, $stackPtr, $type, $data);
        } else {
            $phpcsFile->addWarning($error, $stackPtr, $type, $data);
        }

    }//end addError()


}//end class
