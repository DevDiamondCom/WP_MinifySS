<?php
/*
  Copyright (C) 2016 DevDiamond. (email : me@devdiamond.com)

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/
/**
 * Class WP_MinifySS  - Minify WP Scripts and Styles
 *
 *      global $wpmss;
 *      $wpmss = new WP_MinifySS( array(
 *          'is_js_parser'  => true,
 *          'is_css_parser' => true,
 *          'js_ttl_day'    => 10,
 *          'js_ttl_hour'   => 0,
 *          'js_ttl_min'    => 0,
 *          'js_update'     => '2016-10-05 01:58',
 *          'css_ttl_day'   => 10,
 *          'css_ttl_hour'  => 0,
 *          'css_ttl_min'   => 0,
 *          'css_update'    => '2016-10-05 01:58',
 *      ));
 *
 * @link    https://github.com/DevDiamondCom/WP_MinifySS
 * @version 1.1.0
 * @author  DevDiamond <me@devdiamond.com>
 * @license GPLv2 or later
 */
class WP_MinifySS
{
	private $plugin_name       = 'WP Minify Scripts and Styles';
    private $upload_folder     = '/minify_ss/';
	private $cache_option_slug = 'wpmss_minify_ss';

	private $default_options;
    private $upload_url;
    private $upload_path;
    private $active;
    private $ABSPATH;
	private $is_js_parser;
	private $is_css_parser;

	private $_messages = array();
	private $_allowed_message_types = array('info', 'warning', 'error');

	/**
	 * WP_MinifySS constructor.
	 *
	 * @param array $args  - defoult param {'ttl_day' => 10, 'ttl_hour' => 0, 'ttl_min' => 0}
	 */
    function __construct( $args = array() )
    {
	    $this->is_js_parser = (bool) (isset($args['is_js_parser']) ? $args['is_js_parser'] : true);
	    $this->is_css_parser = (bool) (isset($args['is_css_parser']) ? $args['is_css_parser'] : true);

	    if ( ! $this->is_js_parser && ! $this->is_css_parser )
	    	return;

    	// JS options
	    $this->default_options['js_ttl_day']  = (int) (@$args['js_ttl_day'] ?: 10);
	    $this->default_options['js_ttl_hour'] = (int) (@$args['js_ttl_hour'] ?: 0);
	    $this->default_options['js_ttl_min']  = (int) (@$args['js_ttl_min'] ?: 0);
	    $this->default_options['js_update']   = (string) (@$args['js_update'] ?: '2016-10-05 01:58');

	    // CSS options
		$this->default_options['css_ttl_day']  = (int) (@$args['css_ttl_day'] ?: 10);
	    $this->default_options['css_ttl_hour'] = (int) (@$args['css_ttl_hour'] ?: 0);
	    $this->default_options['css_ttl_min']  = (int) (@$args['css_ttl_min'] ?: 0);
	    $this->default_options['css_update']   = (string) (@$args['css_update'] ?: '2016-10-05 01:58');

	    // Upload URL and PATH
	    if ( get_option( 'upload_url_path' ) )
        {
            $this->upload_url  = get_option( 'upload_url_path' );
            $this->upload_path = get_option( 'upload_path' );
        }
        else
        {
            $up_dir = wp_upload_dir();
            $this->upload_url  = $up_dir['baseurl'];
            $this->upload_path = $up_dir['basedir'];
        }
	    $this->ABSPATH = rtrim(ABSPATH, '/');

	    // Check write directory
        if ( ! is_writable( $this->upload_path . $this->upload_folder ) && is_dir( $this->upload_path . $this->upload_folder ) )
	        $this->add_message( 'error', 'Please set write permissions for "'. $this->upload_path . $this->upload_folder .'"' );
        elseif ( @!mkdir( $this->upload_path . $this->upload_folder, 0777 ) && ! is_dir( $this->upload_path . $this->upload_folder ) )
            $this->add_message( 'error', 'Could not create directory "minify_ss". Please set write permissions for "'. $this->upload_path . $this->upload_folder .'"'  );

	    if ( ! is_writable( $this->upload_path . $this->upload_folder ) || is_admin() )
		    return;

	    // Head actions
	    remove_action('wp_head', 'wp_print_head_scripts', 9);
	    remove_action('wp_head', 'wp_print_styles', 8);
	    add_action('wp_head', array( $this, 'wp_print_head_scripts' ), 9 );
	    add_action('wp_head', array( $this, 'wp_print_head_styles' ), 8 );

	    // Footer actions
	    remove_action('wp_print_footer_scripts', '_wp_footer_scripts');
	    add_action('wp_print_footer_scripts', array( $this, 'wp_print_footer_styles' ) );
	    add_action('wp_print_footer_scripts', array( $this, 'wp_print_footer_scripts' ) );
    }

	/**
	 * Print head scripts
	 */
	public function wp_print_head_scripts()
	{
		if ( ! $this->is_js_parser )
		{
			wp_print_head_scripts();
			return;
		}
		$this->active = 'js';
		$this->wp_print_scripts( 0 );
	}

	/**
	 * Print head styles
	 */
	public function wp_print_head_styles()
	{
		if ( ! $this->is_css_parser )
		{
			wp_print_styles();
			return;
		}
		$this->active = 'css';
		$this->wp_print_styles( 0 );
	}

	/**
	 * Print footer scripts
	 */
	public function wp_print_footer_scripts()
	{
		if ( ! $this->is_js_parser )
		{
			print_footer_scripts();
			return;
		}
		$this->active = 'js';
		$this->wp_print_scripts( 1 );
	}

	/**
	 * Print footer styles
	 */
	public function wp_print_footer_styles()
	{
		if ( ! $this->is_css_parser )
		{
			print_late_styles();
			return;
		}
		$this->active = 'css';
		$this->wp_print_styles( 1 );
	}

	/**
	 * Print scripts
	 *
	 * @param int $group - - Script print zone. [0 => Header zone], [1 => Footer zone]
	 */
	private function wp_print_scripts( $group )
	{
		if ( ! is_writable( $this->upload_path . $this->upload_folder ) || is_admin() )
			return;

		if ( $group === 0 )
		{
			/** This action is documented in wp-includes/functions.wp-scripts.php */
			if ( ! did_action('wp_print_scripts') )
				do_action( 'wp_print_scripts' );
		}

		global $wp_scripts;

		if ( ! ( $wp_scripts instanceof WP_Scripts ) )
			return;

		$wp_scripts->all_deps( $wp_scripts->queue );

		# URL and PATH
		$ss_file_routes = $this->file_routes( $wp_scripts, 'js' );

		# check cache script
		$is_cache_file = $this->is_ss_file( $ss_file_routes['ss_path'] );

		# scripts list
		foreach ( $wp_scripts->to_do as $key => $handle )
		{
			if ( in_array($handle, $wp_scripts->done, true) || ! isset($wp_scripts->registered[$handle]) )
				continue;

			if ( $this->scripts_do_item( $handle, $group, $is_cache_file, $ss_file_routes['ss_path'] ) )
				$wp_scripts->done[] = $handle;

			unset( $wp_scripts->to_do[$key] );
		}

		# print cache script
		if ( $is_cache_file || is_file($ss_file_routes['ss_path']) )
			echo "<script type='text/javascript' src='{$ss_file_routes['ss_url']}'></script>";

		if ( apply_filters( 'print_head_scripts', true ) )
			_print_scripts();

		$wp_scripts->reset();
	}

	/**
	 * Scripts save in cache or print
	 *
	 * @param string $handle        - Script handle
	 * @param int    $group         - Script print zone. [0 => Header zone], [1 => Footer zone]
	 * @param bool   $is_cache_file - Whether there is acting cache file
	 * @param string $ss_path       - Cache file PATH
	 *
	 * @return bool
	 */
	private function scripts_do_item( $handle, $group, $is_cache_file, $ss_path )
	{
		global $wp_scripts;

		if ( $group === 0 && $wp_scripts->groups[ $handle ] > 0 )
		{
			$wp_scripts->in_footer[] = $handle;
			return false;
		}

		$obj = $wp_scripts->registered[ $handle ];

		$cond_before = $cond_after = '';
		$conditional = isset( $obj->extra['conditional'] ) ? $obj->extra['conditional'] : '';

		if ( $conditional )
		{
			$cond_before = "<!--[if {$conditional}]>\n";
			$cond_after = "<![endif]-->\n";
		}

		if ( ($data_handle = $wp_scripts->get_data( $handle, 'data' )) )
		{
			if ( $conditional )
			{
				echo $cond_before;
				echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
				echo "/* <![CDATA[ */\n";
				echo "$data_handle\n";
				echo "/* ]]> */\n";
				echo "</script>\n";
				echo $cond_after;
			}
			elseif ( ! $is_cache_file )
			{
				$this->js_valid_parse($data_handle);
				$this->compression_js($data_handle);
				file_put_contents( $ss_path, $data_handle, FILE_APPEND | LOCK_EX );
			}
			unset($data_handle);
		}

		if ( ! $obj->src )
			return true;

		if ( ! $conditional && $is_cache_file )
			return true;

		$src = $obj->src;
		if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( $wp_scripts->content_url && 0 === strpos( $src, $wp_scripts->content_url ) ) )
			$src = $wp_scripts->base_url . $src;

		/** This filter is documented in wp-includes/class.wp-scripts.php */
		$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );

		if ( ! $src )
			return true;

		$before_handle = $wp_scripts->print_inline_script( $handle, 'before', false );
		$after_handle = $wp_scripts->print_inline_script( $handle, 'after', false );

		if ( $before_handle )
			$before_handle = sprintf( "<script type='text/javascript'>\n%s\n</script>\n", $before_handle );

		if ( $after_handle )
			$after_handle = sprintf( "<script type='text/javascript'>\n%s\n</script>\n", $after_handle );

		if ( $conditional )
		{
			echo "{$cond_before}{$before_handle}<script type='text/javascript' src='$src'></script>\n{$after_handle}{$cond_after}";
			return true;
		}

		if ( $is_cache_file )
			return true;

		$context = NULL;
		if ( $wp_scripts->base_url && strpos( $src, $wp_scripts->base_url ) !== false )
			$src = str_replace( $wp_scripts->base_url, $this->ABSPATH, $src );
		else
		{
			$context = stream_context_create( $this->options_context_HTTP() );
			$src = str_replace('/&amp;#038;/', '&', $src);
		}

		if ( false !== ($f_content = @file_get_contents( $src, NULL, $context )) )
		{
			$this->js_valid_parse($before_handle);
			$this->js_valid_parse($f_content);
			$this->js_valid_parse($after_handle);
			$this->compression_js($before_handle);
			$this->compression_js($f_content);
			$this->compression_js($after_handle);
			file_put_contents(
				$ss_path,
				$before_handle . $f_content . $after_handle,
				FILE_APPEND | LOCK_EX
			);
			return true;
		}

		return true;
	}

	/**
	 * Print styles
	 *
	 * @param  int $group - Script print zone. [0 => Header zone], [1 => Footer zone]
	 */
	private function wp_print_styles( $group )
	{
		if ( ! is_writable( $this->upload_path . $this->upload_folder ) || is_admin() )
			return;

		if ( $group === 0 )
			do_action( 'wp_print_styles' );

		global $wp_styles;
		if ( ! ( $wp_styles instanceof WP_Styles ) )
			return;

		$wp_styles->all_deps( $wp_styles->queue );

		# URL and PATH
		$ss_file_routes = $this->file_routes( $wp_styles, 'css' );

		# check cache style
		$is_cache_file = $this->is_ss_file( $ss_file_routes['ss_path'] );

		# styles list
		foreach ( $wp_styles->to_do as $key => $handle )
		{
			if ( in_array($handle, $wp_styles->done, true) || ! isset($wp_styles->registered[$handle]) )
				continue;

			if ( $this->styles_do_item( $handle, $is_cache_file, $ss_file_routes['ss_path'] ) )
				$wp_styles->done[] = $handle;

			unset( $wp_styles->to_do[$key] );
		}

		# print cache styles
		if ( $is_cache_file || is_file($ss_file_routes['ss_path']) )
			echo "<link href='{$ss_file_routes['ss_url']}' rel='stylesheet' type='text/css' media='all' />";

		if ( apply_filters( 'print_late_styles', true ) ) {
			_print_styles();
		}

		$wp_styles->reset();
	}

	/**
	 * Style save in cache or print
	 *
	 * @param string $handle        - Style handle
	 * @param bool   $is_cache_file - Whether there is acting cache file
	 * @param string $ss_path       - Cache file PATH
	 *
	 * @return bool
	 */
	private function styles_do_item( $handle, $is_cache_file, $ss_path )
	{
		global $wp_styles;

		$obj = $wp_styles->registered[$handle];

		$conditional_pre = $conditional_post = '';
		$conditional = isset( $obj->extra['conditional'] ) && $obj->extra['conditional'];

		if ( $conditional )
		{
			$conditional_pre  = "<!--[if {$obj->extra['conditional']}]>\n";
			$conditional_post = "<![endif]-->\n";
		}

		if ( ($inline_style = $wp_styles->print_inline_style( $handle, false )) && ! $obj->src )
		{
			if ( $conditional )
			{
				echo $conditional_pre;
				echo sprintf( "<style type='text/css'>\n%s\n</style>\n", $inline_style );
				echo $conditional_post;
			}
			elseif ( ! $is_cache_file )
			{
				$this->compression_css( $inline_style );
				file_put_contents( $ss_path, $inline_style . "\n\n", FILE_APPEND | LOCK_EX );
				unset($inline_style);
			}
			return true;
		}
		elseif ( ! $obj->src )
			return true;

		if ( ! $conditional && $is_cache_file )
			return true;

		$href = $wp_styles->_css_href( $obj->src, '', $handle );
		if ( ! $href )
			return true;

		$tag = "<link href='$href' rel='stylesheet' type='text/css' media='all' />\n";

		if ( 'rtl' === $wp_styles->text_direction && isset($obj->extra['rtl']) && $obj->extra['rtl'] )
		{
			if ( is_bool( $obj->extra['rtl'] ) || 'replace' === $obj->extra['rtl'] )
			{
				$suffix = isset( $obj->extra['suffix'] ) ? $obj->extra['suffix'] : '';
				$rtl_href = str_replace( "{$suffix}.css", "-rtl{$suffix}.css", $wp_styles->_css_href( $obj->src , '', "$handle-rtl" ));
			}
			else
			{
				$rtl_href = $wp_styles->_css_href( $obj->extra['rtl'], '', "$handle-rtl" );
			}

			/** This filter is documented in wp-includes/class.wp-styles.php */
			$rtl_tag = apply_filters( 'style_loader_tag', "<link href='$rtl_href' rel='stylesheet' type='text/css' media='all' />\n", $handle, $rtl_href, 'all' );

			if ( $obj->extra['rtl'] === 'replace' )
			{
				$tag = $rtl_tag;
				$href = $rtl_href;
				unset($rtl_href);
			}
			else
				$tag .= $rtl_tag;
		}

		if ( $conditional )
		{
			echo $conditional_pre;
			echo $tag;
			echo $inline_style ? sprintf( "<style type='text/css'>\n%s\n</style>\n", $inline_style ) : '';
			echo $conditional_post;
		}
		elseif ( ! $is_cache_file )
		{
			$context = NULL;
			if ( $wp_styles->base_url && strpos( $href, $wp_styles->base_url ) !== false )
				$href = str_replace( $wp_styles->base_url, $this->ABSPATH, $href );
			else
			{
				$context = stream_context_create( $this->options_context_HTTP() );
				$href = str_replace('/&amp;#038;/', '&', $href);
			}

			if ( false !== ($f_content = file_get_contents( $href, NULL, $context )) )
			{
				$this->css_url_parse( $f_content, $href );
				$this->compression_css( $f_content );
				file_put_contents( $ss_path, $f_content . "\n\n", FILE_APPEND | LOCK_EX );
				unset($f_content);
			}

			if ( isset($rtl_href) )
			{
				$context = NULL;
				$href = $rtl_href;
				if ( $wp_styles->base_url && strpos( $rtl_href, $wp_styles->base_url ) !== false )
					$href = str_replace( $wp_styles->base_url, $this->ABSPATH, $rtl_href );
				else
				{
					$context = stream_context_create( $this->options_context_HTTP() );
					$href = str_replace('/&amp;#038;/', '&', $href);
				}

				if ( false !== ($f_content = @file_get_contents( $href, NULL, $context )) )
				{
					$this->css_url_parse( $f_content, $href );
					$this->compression_css( $f_content );
					file_put_contents( $ss_path, $f_content . "\n\n", FILE_APPEND | LOCK_EX );
					unset($f_content);
				}
			}

			if ( $inline_style )
			{
				$this->compression_css( $inline_style );
				file_put_contents( $ss_path, $inline_style . "\n\n", FILE_APPEND | LOCK_EX );
				unset($inline_style);
			}
		}

		return true;
	}

	/**
	 * Options of the HTTP params
	 *
	 * @return array - stream HTTP params
	 */
	private function options_context_HTTP()
	{
		return array('http' => array(
             'method' => 'GET',
             'header' => 'Accept:'. $_SERVER['HTTP_ACCEPT'] ."\r\n"
	             ."Upgrade-Insecure-Requests:". $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] ."\r\n"
	             ."User-Agent:".$_SERVER['HTTP_USER_AGENT'],
             'max_redirects' => '0',
             'ignore_errors' => '1'
        ));
	}

	/**
	 * Cache file routes
	 *
	 * @param object $obj - extends Class WP_Dependencies
	 * @param string $ext - extensions cache file
	 *
	 * @return array - {'ss_path' => 'PATH cache file', 'ss_url' => 'URL cache file'}
	 */
	private function file_routes( &$obj, $ext )
	{
		$file_name = md5(
			implode( ',', array_keys( array_filter( $obj->groups, function($v){return ! ((bool) $v);}) ) )
			. $this->default_options[ $this->active.'_update']
		);

		return array(
			'ss_path' => $this->upload_path . $this->upload_folder . $file_name .'.'. $ext,
			'ss_url'  => $this->upload_url . $this->upload_folder . $file_name .'.'. $ext,
		);
	}

	/**
	 * Is cache file
	 *
	 * @param  string $ss_path - PATH cache file
	 * @return bool
	 */
	private function is_ss_file( $ss_path )
	{
		if ( ! is_file($ss_path) )
			return false;

		if ( (time() - filemtime($ss_path)) > $this->cache_to_second() )
		{
			if ( ! @unlink( $ss_path ) )
				@file_put_contents( $ss_path, '' );
			return false;
		}

		return true;
	}

	/**
	 * CSS URL parse
	 *
	 * @param string $css_styles - css styles (content)
	 * @param string $url        - current css url or path
	 */
	private function css_url_parse( &$css_styles, $url )
	{
		if ( preg_match( '|^(https?:)?//|', $url ) )
			return;

		$css_styles = preg_replace_callback('/url\(([^)]+)\)/i', function($m) use ( $url )
		{
			$src = trim($m[1], '\'" ');
			if ( preg_match( '#^((https?:)?//|data:)+#', $src ) )
				return $m[0];
			if ( count($arr_src = explode('?', $src, 2)) === 2)
				$arr_src[1] = '?'.$arr_src[1];
			elseif ( count($arr_src = explode('#', $src, 2)) === 2)
				$arr_src[1] = '#'.$arr_src[1];
			if ( ($relpath = realpath( pathinfo($url, PATHINFO_DIRNAME) . '/' . $arr_src[0] )) )
				return "url('".preg_replace('|\\\|', '/', str_replace($this->ABSPATH, '', $relpath)).( @$arr_src[1] ?: '' )."')";
			return $m[0];
		}, $css_styles);
	}

	private function js_valid_parse( &$js_code )
	{
		$js_code = preg_replace('/;?(\n|\s)*$/', '', $js_code) . ";\n\n";
	}

	/**
	 * CSS compression
	 *
	 * @param $css_content
	 */
	private function compression_css( &$css_content )
	{
		$css_content = preg_replace_callback('|/\*(.*?)\*/|s', function($m)
		{
			if ( preg_match('/license/i', $m[1]) )
				return $m[0];
			return '';
		}, $css_content);
		$css_content = preg_replace("/\r\n|\r/", "\n", $css_content);
		$css_content = preg_replace("/\n(\t|\s)+/", "\n", $css_content);
		$css_content = preg_replace("/(\t|\s)+\n/", "\n", $css_content);
		$css_content = preg_replace("/:(\t|\s)+/", ":", $css_content);
		$css_content = preg_replace("/\}\n+/", '}', $css_content);
		$css_content = preg_replace("/\{\n+/", '{', $css_content);
		$css_content = preg_replace("/,\n+/", ',', $css_content);
		$css_content = preg_replace("/;\n+/", ';', $css_content);
		$css_content = preg_replace("/;}/", '}', $css_content);
	}

	/**
	 * JS Compression
	 *
	 * @param string $js_content - JS parse code
	 */
	private function compression_js( &$js_content )
	{
		return;

		# cleaning comments
		$js_content = preg_replace("#\r\n|\r#", "\n", $js_content);
		$new = '';
		while ( preg_match('#.*(//|/\*|[\'"/])#Us', $js_content, $match, PREG_OFFSET_CAPTURE) )
		{
			$case = $match[1][0];
			$pos = $match[1][1] + 1;
			if ($case == '//')
				$js_content = preg_replace('#\/\/.*(\n?)$#m', "$1", $js_content, 1);
			elseif ($case == '/*')
				$js_content = preg_replace('#\/\*.*\*\/#Us', '', $js_content, 1);
			else
			{
				$new .= $match[0][0];
				$ptn = "#.*?(\\\*)($case)#";
				$flag = 1;
				while ( $flag == 1 )
				{
					$flag = preg_match($ptn, $js_content, $match, PREG_OFFSET_CAPTURE, $pos);
					if ( strlen($match[1][0])%2 == 1)
						$pos = $match[2][1]+1;
					else
						$flag = 'break';
					$new .= $match[0][0];
				}
				if ( ! $flag )
					break; // ERROR: Not found an opening quotation mark. Aborting sweep.
				$js_content = substr($js_content, $match[2][1]+1);
			}
		}
		$new .= $js_content;
		$js_content = preg_replace("#\s+\n+#","\n", $new);

		# clearing of unnecessary symbols
		$js_content = preg_replace('#\t#', '', $js_content);
		$js_content = preg_replace("#\n+\s+#", "\n", $js_content);
		$from = $offset = 0;
		$new = '';
		$len = strlen($js_content);
		do {
			$offset = $from + 450;
			if ($offset < $len)
				$pos = strpos($js_content,"\n",$offset);
			else
				$pos = $len;
			if ($pos === false)
				$pos = $len;
			$sub = substr($js_content,$from,$pos-$from);
			$new .= preg_replace("#\n#",'',$sub)."\n";
			$from = $pos + 1;
		} while ($pos != $len);
		$js_content = substr($new,0,-1);
	}

    /**
     * Convert ttl option to second
     */
    private function cache_to_second()
    {
	    $cache_second = 0;
        foreach ( $this->default_options as $key => $value )
        {
            switch ( $key )
            {
                case $this->active.'_ttl_min':
                    $cache_second = ($value != 0 ? ($value*60) : $cache_second);
                    break;
                case $this->active.'_ttl_hour':
                    $cache_second = ($value != 0 ? (( $value*60*60 ) + $cache_second) : $cache_second);
                    break;
                case $this->active.'_ttl_day':
                    $cache_second = ($value != 0 ? (( $value*60*60*24 ) + $cache_second) : $cache_second);
                    break;
            }
        }

        if ( $cache_second == 0 )
            return 864000; // TTL of cache in seconds (10 days)
		else
	        return $cache_second;
    }

	/**
	 * Add notice text
	 *
	 * @param string $type - Message type
	 * @param string $text - Text message
	 */
	private function add_message($type, $text)
	{
		if ( in_array($type, $this->_allowed_message_types))
			$this->_messages[$type][] = $text;
		else
			$this->_messages['error'][] = 'Message not added!';
	}

	/**
	 * Admin page help notices
	 */
	public function admin_help_notice()
	{
		if( empty( $this->_messages ) )
			return;

		foreach ( $this->_messages as $type => $contents )
		{
			if ( $type == 'error' )
			{
				echo '<div class="'. $type .' fade">';
				foreach ( $contents as $content )
					echo '<p><strong>'. $this->plugin_name .': </strong>' . $content . '</p>';
				echo '</div>';
			}
			elseif ( $type != 'error' )
			{
				echo '<div class="updated fade">';
				foreach ( $contents as $content )
					echo '<p><strong>'. ucfirst($type) .': </strong>' . $content . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Get Cached Avatar
	 *
	 * @param $source
	 * @param $id_or_email
	 * @param $size
	 * @param $default
	 * @param $alt
	 *
	 * @return string - HTML IMG
	 */
    public function get_cached_avatar( $source, $id_or_email, $size, $default, $alt )
    {
        if ( ! is_writable( $this->upload_path . $this->upload_folder ) || is_admin() )
            return $source;

	    $time = $this->cache_to_second();

        preg_match('/d=([^&]*)/', $source, $d_tmp);
        $g_url_default_sorce = isset($d_tmp[1]) ? $d_tmp[1] : false;

        preg_match('/forcedefault=([^&]*)/', $source, $d_tmp);
        $g_forcedefault = isset($d_tmp[1]) ? $d_tmp[1] : false;

        preg_match('/avatar\/([a-z0-9]+)\?s=(\d+)/', $source, $tmp);
        $garvatar_id = $tmp[1];

        $file_name      = md5($garvatar_id.$g_url_default_sorce);
        $g_path         = $this->upload_path.$this->upload_folder.$file_name.'-s'.$size.'.jpg';
        $g_path_default = $this->upload_path.$this->upload_folder.'default'.'-s'.$size.'.jpg';
        $g_url          = $this->upload_url.$this->upload_folder.$file_name.'-s'.$size.'.jpg';
        $g_url_default  = $this->upload_url.$this->upload_folder.'default'.'-s'.$size.'.jpg';

        // Check cache
        static $wpgc_avatars_cache = null;
        if ( $wpgc_avatars_cache === null )
        	$wpgc_avatars_cache = get_option($this->cache_option_slug);
        if ( ! is_array($wpgc_avatars_cache) )
        	$wpgc_avatars_cache = array();

        if ( isset($wpgc_avatars_cache[ $garvatar_id ][ $size ]) )
        {
            $g_url  = $wpgc_avatars_cache[ $garvatar_id ][ $size ]['url'];
            $g_path = $wpgc_avatars_cache[ $garvatar_id ][ $size ]['path'];
        }

        if ( ! is_file($g_path) || (time() - filemtime($g_path)) > $time)
        {
            $curl_url = 'http://www.gravatar.com/avatar/'.$garvatar_id.'?s='.$size.'&r=G&d='.$g_url_default_sorce;

            $ch = curl_init($curl_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $response    = curl_exec($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header      = substr($response, 0, $header_size);

            // Checking for redirect
            $header_array = array();
            preg_match('/^Location\: (.*)$/m', $header, $header_array);
            $redirect_url = isset($header_array[1]) ? $header_array[1] : false;

            if ($redirect_url)
            {
                $g_url  = $g_url_default;
                $g_path = $g_path_default;
                if ( ! is_file($g_path) || (time()-filemtime($g_path)) > $time )
                    copy($redirect_url, $g_path);
            }
            else
            {
                // Check mime type
                $mime_str   = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
                $mime_array = array();
                preg_match( '#/([a-z]*)#i', $mime_str, $mime_array );

	            if ( isset( $mime_array[ 1 ] ) )
	            {
		            $img_ext = array('jpg', 'gif', 'jpeg', 'png');

		            if ( false !== strpos( $g_path, '.' ) )
		            {
			            $ext = explode('.', $g_path);
                        $ext = $ext[count($ext)-1];
		            }
		            else
			            $ext = ' ';

		            if ( is_string( $ext ) )
		            {
			            if ( in_array( strtolower($ext), $img_ext ) )
			            {
				            if ( is_writable( $this->upload_path . $this->upload_folder ) )
				            {
					            $fp   = fopen( $g_path, "wb" );
					            $body = substr( $response, $header_size );
					            fwrite( $fp, $body );
					            fclose( $fp );
				            }
			            }
			            else
			            {
				            $this->add_message( 'error', 'Please set write permissions for "' . $this->upload_path . $this->upload_folder .'"' );
			            }
		            }

	            }
            }
            curl_close($ch);

            $wpgc_avatars_cache[ $garvatar_id ][ $size ]['url']  = $g_url;
            $wpgc_avatars_cache[ $garvatar_id ][ $size ]['path'] = $g_path;
            update_option( $this->cache_option_slug, $wpgc_avatars_cache );
        }

        return '<img alt="'.$alt.'" src=\''.$g_url.'\' class="avatar avatar-'.$size.'" width="'.$size.'" height="'.$size.'" />';
    }

	/**
	 * Clear Cache
	 */
    public function clear_cache()
    {
        $dir = $this->upload_path . $this->upload_folder;
        $no_permision_to_delete = false;

        // Open directory
        if ( is_dir( $dir ) )
        {
            if ( $opendir = opendir( $dir ) )
            {
                $count = 0;
                while ( ( $file = readdir( $opendir ) ) !== false )
                {
                    if ( filetype( $dir . $file ) == 'file' )
                    {
                        if ( @unlink( $dir . $file ) )
                            $count++;
                        else
                            $no_permision_to_delete = true;
                    }
                }
                if ( $no_permision_to_delete )
                {
	                $this->add_message( 'error','Unable to clear the cache' );
                }
                else
                {
                    update_option($this->cache_option_slug, array() );
	                $this->add_message( 'info','The cache is cleared! Removed '.$count.' files' );
                }
                closedir( $opendir );
            }
        }
	}

	/**
	 * Get Cache Info
	 *
	 * @return array - Cache info
	 */
    public function get_cache_info()
    {
		$dir  = $this->upload_path . $this->upload_folder;
		$skip = array('.','..');
		$unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');

        if ( is_dir( $dir ) )
        {
			$file_list = scandir( $dir );

			// delete . and ..
			foreach ( $skip as $value )
				unset( $file_list[ array_search( $value, $file_list ) ] );

			// sum files size
			$all_size = 0;
			foreach ( $file_list as $file )
			{
				$size     = filesize( $dir . $file );
			    $all_size = $all_size + $size;
			}
        }

	    $readable_form = @round( $all_size / pow( 1024, ( $i = floor( log( $all_size, 1024) ) ) ), 2 ) . ' ' . $unit[$i];

	    return array( 'amount' => count( $file_list ) , 'used_space' => $readable_form );
	}

} // End Class