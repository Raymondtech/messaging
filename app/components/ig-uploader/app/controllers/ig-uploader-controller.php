<?php
/**
 * Author: Hoang Ngo
 */
if (!class_exists('IG_Uploader_Controller')) {
    class IG_Uploader_Controller extends IG_Request
    {
        public function __construct($can_upload = false)
        {
            if (is_user_logged_in()) {
                if ($can_upload) {
                    add_action('wp_loaded', array(&$this, 'handler_upload'));
                    add_action('wp_ajax_igu_file_delete', array(&$this, 'delete_file'));
                    add_action('wp_ajax_iup_load_upload_form', array(&$this, 'load_upload_form'));
                } else {
                    add_filter('igu_single_file_template', array(&$this, 'single_file_template'));
                }
            }
        }

        function single_file_template()
        {
            return '_single_file_land';
        }

        function load_upload_form()
        {
            if (!wp_verify_nonce(mmg()->post('_wpnonce'), 'iup_load_upload_form')) {
                return;
            }
            $id = mmg()->post('id');
            $model = null;
            if ($id !== null) {
                $model = IG_Uploader_Model::model()->find($id);
            }
            if (!is_object($model)) {
                $model = new IG_Uploader_Model();
            }
            $this->render_partial('_uploader_form', array(
                'model' => $model
            ));
            exit;
        }

        function delete_file()
        {
            if (!wp_verify_nonce(mmg()->post('_wpnonce'), 'igu_file_delete')) {
                return;
            }

            $model = IG_Uploader_Model::model()->find(mmg()->post('id', 0));
            if (is_object($model)) {
                $model->delete();
            }
            exit;
        }

        function handler_upload()
        {
            if (mmg()->get('igu_uploading')) {
                if (!wp_verify_nonce(mmg()->post('_wpnonce'), 'igu_uploading')) {
                    return;
                }
                $model = '';
                $id = mmg()->post('IG_Uploader_Model[id]', 0);
                if ($id != 0) {
                    $model = IG_Uploader_Model::model()->find($id);
                }
                if (!is_object($model)) {
                    $model = new IG_Uploader_Model();
                }
                $model->import(mmg()->post('IG_Uploader_Model'));
                if (isset($_FILES['IG_Uploader_Model'])) {
                    $uploaded = $this->rearrange($_FILES['IG_Uploader_Model']);
                    if (!empty($uploaded['file']['name'])) {
                        $model->file_upload = $uploaded;
                    }
                }
                if ($model->validate()) {

                    $model->save();
                    wp_send_json(array(
                        'status' => 'success',
                        'html' => $this->render_single_file($model, true),
                        'id' => $model->id
                    ));
                } else {
                    wp_send_json(array(
                        'status' => 'fail',
                        'errors' => $model->get_errors()
                    ));
                }
                exit;
            }
        }

        public function upload_form($attribute, $target_model, $container)
        {
            $runtime_path = mmg()->can_compress();

            /*if ($runtime_path) {
                mmg()->compress_assets(array('igu-uploader'), array('popoverasync', 'jquery-frame-transport'), $runtime_path);
            } else {
                wp_enqueue_style('igu-uploader');
                wp_enqueue_script('popoverasync');
                wp_enqueue_script('jquery-frame-transport');
            }*/
            $ids = $target_model->$attribute;
            $models = array();
            if (!is_array($ids)) {
                $ids = explode(',', $ids);
                $ids = array_filter(array_unique($ids));
            }
            if (!empty($ids)) {
                $models = IG_Uploader_Model::all_with_condition(array(
                    'status' => 'publish',
                    'post__in' => $ids
                ));
            }

            //$models[]=IG_Uploader_Model::model()->find(8);

            $mode = IG_Uploader_Model::MODE_EXTEND;

            if ($mode == IG_Uploader_Model::MODE_LITE) {
                $this->_lite_form();
            } else {
                $this->_extend_form($models, $attribute, $target_model, $container);
            }
        }

        public function render_single_file($model, $return = false)
        {
            if ($return) {
                return $this->render_partial(apply_filters('igu_single_file_template', '_single_file'), array(
                    'model' => $model
                ), false);
            }
            $this->render_partial(apply_filters('igu_single_file_template', '_single_file'), array(
                'model' => $model
            ));
        }

        public function _lite_form()
        {

        }

        public function _extend_form($models, $attribute, $target_model, $container)
        {
            $cid = uniqid();

            $this->render('_extend_form', array(
                'models' => $models,
                'tmodel' => $target_model,
                'attribute' => $attribute,
                'target_id' => $this->build_id($target_model, $attribute),
                'container' => $container
            ));
        }

        function rearrange($arr)
        {
            foreach ($arr as $key => $all) {
                foreach ($all as $i => $val) {
                    $new[$i][$key] = $val;
                }
            }

            return $new;
        }

        private function build_id($model, $attribute)
        {
            $class_name = get_class($model);

            return sanitize_title($class_name . '-' . $attribute);
        }
    }
}