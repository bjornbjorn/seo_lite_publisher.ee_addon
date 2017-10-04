<?php

use BoldMinded\Publisher\Enum\Status;
use BoldMinded\Publisher\Model\Language;
use BoldMinded\Publisher\Service\Channel;
use BoldMinded\Publisher\Service\Entry\Entry;
use BoldMinded\Publisher\Service\Query;
use BoldMinded\Publisher\Service\Request;
use BoldMinded\Publisher\Service\Setting;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * SEO Lite Publisher Extension
 *
 * @package     ExpressionEngine
 * @subpackage  Addons
 * @category    Extension
 * @author      Bjørn Børresen
 * @link        http://wedoaddons.com
 */

class Seo_lite_publisher_ext {

    public $settings = [];
    public $version = SEOLITE_PUBLISHER_VERSION;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Setting
     */
    private $publisherSetting;

    /**
     * @param   mixed Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        $this->settings = $settings;

        $this->request = ee(Request::NAME);
        $this->publisherSetting = ee(Setting::NAME);
        $this->currentLanguageId = $this->request->getCurrentLanguage()->getId();
        $this->defaultLanguageId = $this->request->getDefaultLanguage()->getId();
        $this->currentStatus = $this->request->getCurrentStatus();
        $this->saveStatus = $this->request->getSaveStatus();
    }

    /**
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
        $langId = $this->currentLanguageId;
        $status = $this->currentStatus;

        if (ee()->publisher_setting->show_fallback()) {
            /** CI_DB_result $q */
            $translatedWhere = array(
                'publisher_lang_id' => $langId,
                'publisher_status' => $status,
            );
            if (isset($where['entry_id'])) {
                $translatedWhere['entry_id'] = $where['entry_id'];
            }
            $q = ee()->db->get_where('publisher_seolite_content', $translatedWhere);

            if (!$q->num_rows()) {
                $langId = $this->defaultLanguageId;
            }
        }

        // where arr used w/activerecord
        $where['publisher_lang_id'] = $langId;
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
        $where['publisher_lang_id'] = $this->currentLanguageId;
        $where['publisher_status'] = $this->saveStatus;

        $content['publisher_lang_id'] = $this->currentLanguageId;
        $content['publisher_status'] = $this->saveStatus;

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

    /**
     * @param array $where
     * @param string $table_name
     *
     * @return array
     */
    public function seo_lite_fetch_data($where, $table_name)
    {
        $langId = $this->currentLanguageId;
        $status = $this->currentStatus;

        if ($this->publisherSetting->show_fallback()) {
            /** CI_DB_result $q */
            $translatedWhere = array(
                'publisher_lang_id' => $langId,
                'publisher_status' => $status,
            );
            if (isset($where['t.entry_id'])) {
                $translatedWhere['entry_id'] = $where['t.entry_id'];
            } else if (isset($where['url_title'])) {
                if ($this->publisherSetting->get('url_translations') {
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
                $langId = $this->defaultLanguageId;
            }
        }

        // where arr used w/activerecord
        $where['publisher_lang_id'] = $langId;
        $where['publisher_status'] = $status;

        return array(
            'where' => $where,
            'table_name' => 'publisher_seolite_content' // pull content from Publisher saved data instead of default SEO Lite content
        );
    }


    /**
     * @return void
     */
    public function disable_extension()
    {
        ee()->db->delete('extensions', array('class' => __CLASS__));

        // do not delete the publisher_seolite_content table here to allow for enabling / disabling of extension w/o losing data ..
    }

    /**
     * @return mixed void on update / false if none
     */
    public function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }
    }
}
