<?php
/**
 * WordPress MCP Server functionality.
 *
 * @link       https://github.com/noteflow-ai
 * @since      1.0.7
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * WordPress MCP Server class.
 *
 * This class implements a local MCP server that provides tools to query WordPress content.
 *
 * @since      1.0.7
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Glay <https://github.com/noteflow-ai>
 */
class AI_Chat_Bedrock_WP_MCP_Server {

    /**
     * The available tools.
     *
     * @since    1.0.7
     * @access   private
     * @var      array    $tools    The available tools.
     */
    private $tools;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.7
     */
    public function __construct() {
        $this->init_tools();
        $this->register_routes();
    }

    /**
     * Initialize the available tools.
     *
     * @since    1.0.7
     * @access   private
     */
    private function init_tools() {
        $this->tools = array(
            'search_posts' => array(
                'name' => 'search_posts',
                'description' => 'Search WordPress posts by keyword, category, tag, or author.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query' => array(
                            'type' => 'string',
                            'description' => 'Search query keyword.',
                        ),
                        'category' => array(
                            'type' => 'string',
                            'description' => 'Category name or ID.',
                        ),
                        'tag' => array(
                            'type' => 'string',
                            'description' => 'Tag name or ID.',
                        ),
                        'author' => array(
                            'type' => 'string',
                            'description' => 'Author name or ID.',
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Maximum number of posts to return.',
                        ),
                    ),
                    'required' => array(),
                ),
            ),
            'get_post' => array(
                'name' => 'get_post',
                'description' => 'Get a specific WordPress post by ID or slug.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'id' => array(
                            'type' => 'integer',
                            'description' => 'Post ID.',
                        ),
                        'slug' => array(
                            'type' => 'string',
                            'description' => 'Post slug.',
                        ),
                    ),
                    'required' => array(),
                ),
            ),
            'get_categories' => array(
                'name' => 'get_categories',
                'description' => 'Get WordPress categories.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'hide_empty' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to hide empty categories.',
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Maximum number of categories to return.',
                        ),
                    ),
                    'required' => array(),
                ),
            ),
            'get_tags' => array(
                'name' => 'get_tags',
                'description' => 'Get WordPress tags.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'hide_empty' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to hide empty tags.',
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Maximum number of tags to return.',
                        ),
                    ),
                    'required' => array(),
                ),
            ),
            'get_site_info' => array(
                'name' => 'get_site_info',
                'description' => 'Get WordPress site information.',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(),
                    'required' => array(),
                ),
            ),
        );
    }

    /**
     * Register REST API routes for MCP server.
     *
     * @since    1.0.7
     * @access   private
     */
    private function register_routes() {
        add_action('rest_api_init', function () {
            // MCP discovery endpoint
            register_rest_route('mcp', '/discover', array(
                'methods' => 'GET',
                'callback' => array($this, 'handle_discover'),
                'permission_callback' => '__return_true',
            ));

            // MCP health endpoint
            register_rest_route('mcp', '/health', array(
                'methods' => 'GET',
                'callback' => array($this, 'handle_health'),
                'permission_callback' => '__return_true',
            ));

            // MCP tools endpoints
            foreach ($this->tools as $tool_name => $tool) {
                register_rest_route('mcp/tools', '/' . $tool_name, array(
                    'methods' => 'POST',
                    'callback' => array($this, 'handle_tool_' . $tool_name),
                    'permission_callback' => '__return_true',
                ));
            }
        });
    }

    /**
     * Handle MCP discovery request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_discover($request) {
        return rest_ensure_response(array(
            'name' => 'WordPress MCP Server',
            'description' => 'MCP server providing tools to query WordPress content.',
            'version' => '1.0.0',
            'tools' => array_values($this->tools),
        ));
    }

    /**
     * Handle MCP health request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_health($request) {
        return rest_ensure_response(array(
            'status' => 'ok',
            'version' => '1.0.0',
        ));
    }

    /**
     * Handle search_posts tool request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_tool_search_posts($request) {
        $params = $request->get_json_params();
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['limit']) ? intval($params['limit']) : 5,
        );

        if (isset($params['query']) && !empty($params['query'])) {
            $args['s'] = sanitize_text_field($params['query']);
        }

        if (isset($params['category']) && !empty($params['category'])) {
            $category = sanitize_text_field($params['category']);
            if (is_numeric($category)) {
                $args['cat'] = intval($category);
            } else {
                $args['category_name'] = $category;
            }
        }

        if (isset($params['tag']) && !empty($params['tag'])) {
            $tag = sanitize_text_field($params['tag']);
            if (is_numeric($tag)) {
                $args['tag_id'] = intval($tag);
            } else {
                $args['tag'] = $tag;
            }
        }

        if (isset($params['author']) && !empty($params['author'])) {
            $author = sanitize_text_field($params['author']);
            if (is_numeric($author)) {
                $args['author'] = intval($author);
            } else {
                $args['author_name'] = $author;
            }
        }

        $query = new WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = $this->format_post(get_post());
            }
            wp_reset_postdata();
        }

        return rest_ensure_response(array(
            'posts' => $posts,
            'total' => $query->found_posts,
            'max_pages' => $query->max_num_pages,
        ));
    }

    /**
     * Handle get_post tool request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_tool_get_post($request) {
        $params = $request->get_json_params();
        $post = null;

        if (isset($params['id']) && !empty($params['id'])) {
            $post = get_post(intval($params['id']));
        } elseif (isset($params['slug']) && !empty($params['slug'])) {
            $args = array(
                'name' => sanitize_title($params['slug']),
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 1,
            );
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                $post = $query->posts[0];
            }
        }

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }

        return rest_ensure_response(array(
            'post' => $this->format_post($post, true),
        ));
    }

    /**
     * Handle get_categories tool request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_tool_get_categories($request) {
        $params = $request->get_json_params();
        
        $args = array(
            'hide_empty' => isset($params['hide_empty']) ? (bool)$params['hide_empty'] : false,
            'number' => isset($params['limit']) ? intval($params['limit']) : 0,
        );

        $categories = get_categories($args);
        $formatted_categories = array();

        foreach ($categories as $category) {
            $formatted_categories[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'url' => get_category_link($category->term_id),
            );
        }

        return rest_ensure_response(array(
            'categories' => $formatted_categories,
            'total' => count($formatted_categories),
        ));
    }

    /**
     * Handle get_tags tool request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_tool_get_tags($request) {
        $params = $request->get_json_params();
        
        $args = array(
            'hide_empty' => isset($params['hide_empty']) ? (bool)$params['hide_empty'] : false,
            'number' => isset($params['limit']) ? intval($params['limit']) : 0,
        );

        $tags = get_tags($args);
        $formatted_tags = array();

        foreach ($tags as $tag) {
            $formatted_tags[] = array(
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description,
                'count' => $tag->count,
                'url' => get_tag_link($tag->term_id),
            );
        }

        return rest_ensure_response(array(
            'tags' => $formatted_tags,
            'total' => count($formatted_tags),
        ));
    }

    /**
     * Handle get_site_info tool request.
     *
     * @since    1.0.7
     * @param    WP_REST_Request $request The request object.
     * @return   WP_REST_Response         The response object.
     */
    public function handle_tool_get_site_info($request) {
        return rest_ensure_response(array(
            'site_info' => array(
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url' => get_bloginfo('url'),
                'admin_email' => get_bloginfo('admin_email'),
                'language' => get_bloginfo('language'),
                'version' => get_bloginfo('version'),
                'post_count' => wp_count_posts()->publish,
                'page_count' => wp_count_posts('page')->publish,
                'category_count' => wp_count_terms('category'),
                'tag_count' => wp_count_terms('post_tag'),
            ),
        ));
    }

    /**
     * Format a post object for API response.
     *
     * @since    1.0.7
     * @access   private
     * @param    WP_Post $post         The post object.
     * @param    bool    $include_content Whether to include post content.
     * @return   array                 Formatted post data.
     */
    private function format_post($post, $include_content = false) {
        $author = get_userdata($post->post_author);
        $categories = get_the_category($post->ID);
        $tags = get_the_tags($post->ID);
        
        $formatted_categories = array();
        if ($categories) {
            foreach ($categories as $category) {
                $formatted_categories[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                );
            }
        }
        
        $formatted_tags = array();
        if ($tags) {
            foreach ($tags as $tag) {
                $formatted_tags[] = array(
                    'id' => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                );
            }
        }
        
        $formatted_post = array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'date' => get_the_date('c', $post),
            'modified' => get_the_modified_date('c', $post),
            'excerpt' => get_the_excerpt($post),
            'author' => array(
                'id' => $author->ID,
                'name' => $author->display_name,
            ),
            'categories' => $formatted_categories,
            'tags' => $formatted_tags,
            'url' => get_permalink($post),
        );
        
        if ($include_content) {
            $formatted_post['content'] = apply_filters('the_content', $post->post_content);
        }
        
        return $formatted_post;
    }
}
