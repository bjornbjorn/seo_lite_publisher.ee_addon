<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package   ExpressionEngine
 * @author    ExpressionEngine Dev Team
 * @copyright Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license   http://expressionengine.com/user_guide/license.html
 * @link    http://expressionengine.com
 * @since   Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * SEO Lite Publisher Extension
 *
 * @package   ExpressionEngine
 * @subpackage  Addons
 * @category  Extension
 * @author    Bjørn Børresen
 * @link    http://wedoaddons.com
 */

class Seo_lite_publisher_ext {

    public $settings    = array();
    public $description   = 'SEO Lite + Publisher extension';
    public $docs_url    = '';
    public $name      = 'SEO Lite Publisher';
    public $settings_exist  = 'n';
    public $version     = '1.0.1';

    /**
     * Constructor
     *
     * @param   mixed Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        $this->settings = $settings;
    }

    // ----------------------------------------------------------------------

    /**
     * Activate Extension
     *
     * This function enters the extension into the exp_extensions table
     *
     * @see http://codeigniter.com/user_guide/database/index.html for
     * more information on the db class.
     *
     * @return void
     */
    public function activate_extension()
    {
        // Setup custom settings in this array.
        $this->settings = array();

        // check if the Publisher SEO Lite table already exists, if so don't create it.
        if(!ee()->db->table_exists('publisher_seolite_content')) {

            // 1. Create new table for Publisher translated versions of SEO Lite
            ee()->load->dbforge();

            $publisher_seolite_content_fields = array(
                'publisher_seolite_content_id' => array(
                    'type' => 'int',
                    'constraint' => '10',
                    'unsigned' => TRUE,
                    'auto_increment' => TRUE,),
                'site_id' => array(
                    'type' => 'int',
                    'constraint' => '10',
                    'null' => FALSE,),
                'entry_id' => array(
                    'type' => 'int',
                    'constraint' => '10',
                    'null' => FALSE,),
                'title' => array(
                    'type' => 'varchar',
                    'constraint' => '1024',
                    'null' => FALSE,),
                'keywords' => array(
                    'type' => 'varchar',
                    'constraint' => '1024',
                    'null' => FALSE,),
                'description' => array(
                    'type' => 'text',),
                'publisher_status' => array(
                    'type' => 'text',),
                'publisher_lang_id' => array(
                    'type' => 'int',
                    'constraint' => '10',
                    'null' => FALSE,),
            );

            ee()->dbforge->add_field($publisher_seolite_content_fields);
            ee()->dbforge->add_key('publisher_seolite_content_id', TRUE);
            ee()->dbforge->create_table('publisher_seolite_content');
        }

        /**
         * Hook on to SEO Lite
         */
        $hooks = array(
            'seo_lite_tab_content', 'seo_lite_tab_content_save', 'seo_lite_fetch_data'
        );

        foreach($hooks as $hook) {
            ee()->db->insert('extensions', array(
                'class' => __CLASS__,
                'hook' => $hook,
                'method' => $hook,
                'priority' => '10',
                'settings' => serialize($this->settings),
                'version' => $this->version,
                'enabled' => 'y',
            ));
        }
    }


    /* ===========================================================
        SEO Lite support
    ============================================================ */

    // the SEO Lite content to display in the SEO Lite tab
    public function seo_lite_tab_content($where, $table_name)
    {   
        $lang_id = ee()->publisher_lib->lang_id;
        $status = ee()->publisher_lib->status;
        
        if (ee()->publisher_setting->show_fallback()) {
            /** CI_DB_result $q */
            $translatedWhere = array(
                'publisher_lang_id' => $lang_id,
                'publisher_status' => $status,
            );
            if (isset($where['entry_id'])) {
                $translatedWhere['entry_id'] = $where['entry_id'];
            }
            $q = ee()->db->get_where('publisher_seolite_content', $translatedWhere);
            
            if (!$q->num_rows()) {
                $lang_id = ee()->publisher_lib->default_lang_id;
            }
        }
        
        // where arr used w/activerecord
        $where['publisher_lang_id'] = $lang_id;
        $where['publisher_status']  = $status;

        return array(
            'where' => $where,
            'table_name' => 'publisher_seolite_content' // pull content from Publisher saved data instead of default SEO Lite content
        );
    }

    // the SEO Lite content to save
    public function seo_lite_tab_content_save($where, $table_name, $content)
    {
        // where arr used w/activerecord
        $where['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $where['publisher_status']  = ee()->publisher_lib->publisher_save_status;

        $content['publisher_lang_id'] = ee()->publisher_lib->lang_id;
        $content['publisher_status']  = ee()->publisher_lib->publisher_save_status;

        // if no SEO Lite title is specified we save the entry's title here - or else
        // we would get the original language's entry title when getting data w/SEO Lite
        if($content['title'] == '') {
            $content['title'] = ee()->input->post('title');
        }

        return array(
            'where' => $where,
            'table_name' => 'publisher_seolite_content', // save data to this table instead
            'content' => $content,  // additional content
        );
    }

    // called from the frontend
    public function seo_lite_fetch_data($where, $table_name)
    {
        $lang_id = ee()->publisher_lib->lang_id;
        $status = ee()->publisher_lib->status;
        
        if (ee()->publisher_setting->show_fallback()) {
            /** CI_DB_result $q */
            $translatedWhere = array(
                'publisher_lang_id' => $lang_id,
                'publisher_status' => $status,
            );
            if (isset($where['t.entry_id'])) {
                die('1');
                $translatedWhere['entry_id'] = $where['t.entry_id'];
            } else if (isset($where['url_title'])) {
                if (ee()->publisher_setting->url_translations()) {
                    $entry = ee()->db->get_where('publisher_titles', array_merge(
                        $translatedWhere, array('url_title' => $where['url_title'])
                    ));

                    if (!$entry->num_rows()) {
                        $entry = ee()->db->get_where('channel_titles', array(
                            'url_title' => $where['url_title']
                        ));
                    }
                } else {
                    $entry = ee()->db->get_where('channel_titles', array(
                        'url_title' => $where['url_title']
                    ));
                }

                if ($entry->num_rows()) {
                    $translatedWhere['entry_id'] = $entry->row('entry_id');
                }
            }

            $q = ee()->db->get_where('publisher_seolite_content', $translatedWhere);

            if (!$q->num_rows()) {
                $lang_id = ee()->publisher_lib->default_lang_id;
            }
        }
        
        // where arr used w/activerecord
        $where['publisher_lang_id'] = $lang_id;
        $where['publisher_status']  = $status;

        return array(
            'where' => $where,
            'table_name' => 'publisher_seolite_content' // pull content from Publisher saved data instead of default SEO Lite content
        );
    }


    // ----------------------------------------------------------------------

    /**
     * Disable Extension
     *
     * This method removes information from the exp_extensions table
     *
     * @return void
     */
    public function disable_extension()
    {
        ee()->db->delete('extensions', array('class' => __CLASS__));

        // do not delete the publisher_seolite_content table here to allow for enabling / disabling of extension w/o losing data ..
    }

    // ----------------------------------------------------------------------

    /**
     * Update Extension
     *
     * This function performs any necessary db updates when the extension
     * page is visited
     *
     * @return  mixed void on update / false if none
     */
    public function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }
    }

    // ----------------------------------------------------------------------
}

/* End of file ext.seo_lite_publisher.php */
/* Location: /system/expressionengine/third_party/seo_lite_publisher/ext.seo_lite_publisher.php */
