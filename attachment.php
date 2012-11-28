<?php

/**
 * undocumented class
 *
 * @package default
 * @access public
 */


/*
http://debuggable.com/posts/unlimited-model-fields-expandable-behavior:48428c2e-9a88-47ec-ae8e-77a64834cda3

class Upload extends AppModel{
  var $actsAs = array('Expandable');
}

$form->input('Upload.fps')
$form->input('Upload.bitrate')
$form->input('Upload.rating', array('options' => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10)));


$this->Upload->save(array(
  'id' => 1,
  'fps' => 30,
  'rating'= > 7/10,
));

*/


class AttachmentBehavior extends ModelBehavior
{

	var $options = array();
	var $_files  = array();



	function setup(&$Model, $options = array())
	{
		$default = array(
			'fields' => array('photo', 'image', 'file', 'thumb'),
		);

		if (!empty($options['fields']) && !is_array($options['fields']))
			$options['fields'] = array($options['fields']);


		$options = am($default, $options);

		$this->options = $options;
	}



	function beforeDelete(&$model)
	{
		$element = $model->findById($model->id);

		if (!$element)
			return false;

		foreach ($element[$model->name] as $field=>$value)
		{
			if (is_array($value) && !empty($value['path']))
			{
				//echo '<br />unlink: ' . $field . ' - ' . $value['path'];
				_rmdir(dirname($value['path']));
			}
		}

		//p($element[$model->name]);

    	return true;
	}



	function beforeFind(&$model, $query)
	{

		if ($model->name == 'Product')
		{

			$recursive = $model->recursive;

			if (isset($query['recursive']))
			{
				$recursive = $query['recursive'];
			}

			if ($recursive >= 0)
			{
				foreach (array('hasOne', 'belongsTo', 'hasMany') as $type)
				{
					foreach ($model->{$type} as $key => $value)
					{
						if (!empty($model->{$key}->Behaviors->Attachment->options['fields']))
						{
							foreach ($model->{$key}->Behaviors->Attachment->options['fields'] as $field)
							{
								$this->options['fields'][] = $key . '.' . $field;
							}
						}
					}
				}
			}
        }

		return $query;
	}


	function afterFind(&$model, $results, $primary)
	{
		if ($results)
		{
			pf::begin('files-' . $model->name);

			foreach ($results as $k=>$item)
			{
				if (!empty($item[$model->name]))
				{
					foreach ($this->options['fields'] as $field)
					{
						if (strpos($field, '.') == false)
						{
							if (!isset($results[$k][$model->name][$field])) continue;

							$_am = $_af = false;

							$name = !empty($item[$model->name][$field]) ? $item[$model->name][$field] : '';

							$id   = !empty($item[$model->name]['id']) ? $item[$model->name]['id'] : '';

							$folder = !empty($model->imagesFolder) ? $model->imagesFolder : $model->name;

							$results[$k][$model->name][$field] = $this->_getFieldData($id, $name, $folder);
						}
						else
						{
							list($_am, $_af) = explode('.', $field);

							if (isset($model->hasMany[$_am]))
							{
								$folder = !empty($model->{$_am}->imagesFolder) ? $model->{$_am}->imagesFolder : $model->{$_am}->name;

								if (!empty($item[$_am]))
								{
									foreach ($item[$_am] as $ak=>$tmp)
									{
										$name = !empty($item[$_am][$ak][$_af]) ? $item[$_am][$ak][$_af] : '';

										$id   = !empty($item[$_am][$ak]['id']) ? $item[$_am][$ak]['id'] : 0;

										$results[$k][$_am][$ak][$_af] = $this->_getFieldData($id, $name, $folder);
									}
								}
							}
							else
							{
								if (!empty($item[$_am]))
								{
									$name = !empty($item[$_am][$_af]) ? $item[$_am][$_af] : '';

									$id   = !empty($item[$_am]['id']) ? $item[$_am]['id'] : 0;

									$folder = !empty($model->{$_am}->imagesFolder) ? $model->{$_am}->imagesFolder : $model->{$_am}->name;

									$results[$k][$_am][$_af] = $this->_getFieldData($id, $name, $folder);
								}
							}
						}



					}
				}
			}


			pf::end('files-' . $model->name);

		}

		return $results;
	}






	function _getFieldData($id, $name, $folder)
	{
		if (!$name || !$id || !$folder)
			return false;

		$isImage = in_array(strtolower(array_pop(explode('.', $name))), array('jpg', 'jpeg', 'gif', 'png'));

		if ($isImage)
			$path = 'images' . DS;
		else
			$path = 'files' . DS;

		$s = zerofill($id, 6);

		$path .= Inflector::underscore($folder) . DS . substr($s, 0, 3) . DS . $s . DS . $name;


		if (!file_exists(HOME . DS . $path))
		    return false;


        $url = str_replace(DS, '/', $path);
		$file = array(
			'urlAbs' => '/' . $url,
			'path' => HOME . DS . $path
		);

		if ($isImage)
		{
			$ext  = array_pop(explode('.', $url));
			$base = preg_replace('#\..*$#si', '', $file['urlAbs']);

			$h1 = substr(md5(LICENSE_KEY . $base . filemtime($file['path']) . 'tn-150x150'), 0, 10);
			$h2 = substr(md5(LICENSE_KEY . $base . filemtime($file['path']) . 'crop-25x25'), 0, 10);

			$file['urlTn'] = $base . '.tn-150x150.' . $h1 . '.' . $ext;
			$file['urlCrop'] = $base . '.crop-25x25.' . $h2 . '.' . $ext;
			$file['_urlTn'] = rtrim(_HOME, '/') . $file['urlTn'];
			$file['_urlCrop'] = rtrim(_HOME, '/') . $file['urlCrop'];
		}

		return $file;

	}






	function beforeSave(&$model)
	{
		foreach ($this->options['fields'] as $field)
		{
			if ($model->hasField($field))
			{
				if (!isset($this->_files[$model->name])) $this->_files[$model->name] = array();

				if (!empty($model->data[$model->name][$field]))
					$this->_files[$model->name][$field] = $model->data[$model->name][$field];

				unset($model->data[$model->name][$field]);
			}
		}
	}



	function afterSave(&$model)
	{
		$this->uploadFiles($model);
	}


	function uploadFiles(&$model)
	{
		if (!empty($this->_files[$model->name]))
		{
			foreach ($this->_files[$model->name] as $field=>$file)
			{
				$fileName = $this->uploadFile($model, $field, $file);
				if ($fileName)
				{
					unset($this->_files[$model->name][$field]);
					//$model->create(false);
					$model->save(array($model->name => array($field => $fileName)), array('callbacks' => false, 'fieldList' => array($field)));
				}
				else
				{
					// TODO: email on hacking
				}
			}
		}

	}




    function validateExt($fileName)
	{
		return validateExt($fileName);
	}



	function uploadFile(&$model, $field, $file)
	{
		$name = '';
		if (!empty($file['tmp_name']) && !empty($file['size']) && $file['error'] == 0)
		{
			$name = $file['name'];
			$path = $file['tmp_name'];
		}
		elseif (is_string($file) && file_exists($file))
		{
			$name = basename($file);
			$path = $file;
		}
		$valid = $this->validateExt($name);


		if ($valid)
		{
			$isImage = in_array(strtolower(array_pop(explode('.', $name))), array('jpg', 'jpeg', 'gif', 'png'));

            if ($isImage)
            	$dest = HOME . DS . 'images' . DS;
            else
            	$dest = HOME . DS . 'files' . DS;

            $folder = !empty($model->imagesFolder) ? $model->imagesFolder : $model->name;

            $s = zerofill($model->id, 6);

            $dest .= Inflector::underscore($folder) . DS . substr($s, 0, 3) . DS . $s . DS;

			if (is_dir($dest) || mkdir($dest, 0777, true))
			{
				$dest .= strtolower(totranslit(mt_rand(100, 999) . '-' . $name, true, true));
                $isWater = in_array(strtolower($folder ), array('catalog_category', 'photo_category', 'product', 'photo'));
                if($isWater):
                    $watermark_img_obj = imagecreatefrompng( HOME . DS .'images'. DS .'watermark.png' );
                    switch ($file['type']) {
                        case 'image/jpeg':
                            $main_img_obj = imagecreatefromjpeg( $path );
                        break;
                        case 'image/png':
                            $main_img_obj = imagecreatefrompng( $path );
                        break;
                        case 'image/gif':
                            $main_img_obj = imagecreatefromgif( $path );
                        break;
                    }
    		        
                    $return_img_obj = $this->create_watermark( $main_img_obj, $watermark_img_obj, 83 );
                    
                    switch ($file['type']) {
                        case 'image/jpeg':
                            if(imagejpeg( $return_img_obj, $dest, 100 ))
                                return basename($dest);
                        break;
                        case 'image/png':
                            if(imagepng( $return_img_obj, $dest, 100 ))
                                return basename($dest);
                        break;
                        case 'image/gif':
                            if(imagegif( $return_img_obj, $dest, 100 ))
                                return basename($dest);
                        break;
                    }
                
               else:
                    if (copy($path, $dest)) return basename($dest);
               endif;
              //  endif;

			}
		}

	}


	function mkFilePath(&$model, $file)
	{
		$name = $this->escape($file['name']);

		$path = date('Y/m/d') . '/' . $name[0] . $name[1] . $name[2] . '/';

		$path .= $this->escape($name);

		return $path;
	}



	function escape($str)
	{
		return strtolower(totranslit($str, true, true));
	}
    
   	function create_watermark( $main_img_obj, $watermark_img_obj, $alpha_level = 100 )
		{
		    # переводим значение прозрачности альфа-канала из % в десятки
		    $alpha_level/= 100;
            $water_pr = 20; // процент отношения вотермарка к изображению

		    # расчет размеров изображения (ширина и высота)
		    $main_img_obj_w = imagesx( $main_img_obj );
		    $main_img_obj_h = imagesy( $main_img_obj );
		    $watermark_img_obj_w = imagesx( $watermark_img_obj );
		    $watermark_img_obj_h = imagesy( $watermark_img_obj );
            $need_w = $main_img_obj_w * $water_pr /100;
            $need_h = $need_w*$watermark_img_obj_h/$watermark_img_obj_w;
            
            $need_wt = imagecreate($need_w, $need_h);
            
            imagecopyresampled($need_wt,$watermark_img_obj,0,0,0,0,$need_w,$need_h,$watermark_img_obj_w,$watermark_img_obj_h);
            $watermark_img_obj = $need_wt;
            $watermark_img_obj_w = $need_w;
            $watermark_img_obj_h = $need_h;
            

		    # определение координат центра изображения
		    $main_img_obj_min_x=floor(($main_img_obj_w/2)-($watermark_img_obj_w/2));
		    $main_img_obj_max_x=ceil(($main_img_obj_w/2)+($watermark_img_obj_w/2));
		    $main_img_obj_min_y=floor(($main_img_obj_h/2)-($watermark_img_obj_h/2));
		    $main_img_obj_max_y=ceil(($main_img_obj_h/2)+($watermark_img_obj_h/2));

		    # создание нового изображения
		    $return_img = imagecreatetruecolor( $main_img_obj_w, $main_img_obj_h );

		    # пройдемся по изображению
			for( $y = 0; $y < $main_img_obj_h; $y++ )
			{
			    for ($x = 0; $x < $main_img_obj_w; $x++ )
			    {
			        $return_color = NULL;

			        # определение истинного расположения пикселя в пределах
			        # нашего водяного знака
			        $watermark_x = $x - $main_img_obj_min_x;
			        $watermark_y = $y - $main_img_obj_min_y;

			        # выбор информации о цвете для наших изображений
			        $main_rgb = imagecolorsforindex( $main_img_obj,
			                                 imagecolorat( $main_img_obj, $x, $y ) );

			        # если наш пиксель водяного знака непрозрачный
			        if ($watermark_x >= 0 && $watermark_x < $watermark_img_obj_w &&
			            $watermark_y >= 0 && $watermark_y < $watermark_img_obj_h )
			        {
			            $watermark_rbg = imagecolorsforindex( $watermark_img_obj,
			                             imagecolorat( $watermark_img_obj,
			                                           $watermark_x,
			                                           $watermark_y ) );

			            # использование значения прозрачности альфа-канала
			            $watermark_alpha = round(((127-$watermark_rbg['alpha'])/127),2);
			            $watermark_alpha = $watermark_alpha * $alpha_level;

			            # расчет цвета в месте наложения картинок
			            $avg_red = $this->_get_ave_color( $main_rgb['red'],
			                       $watermark_rbg['red'], $watermark_alpha );
			            $avg_green = $this->_get_ave_color( $main_rgb['green'],
			                         $watermark_rbg['green'], $watermark_alpha );
			            $avg_blue = $this->_get_ave_color( $main_rgb['blue'],
			                        $watermark_rbg['blue'], $watermark_alpha );

			            # используя полученные данные, вычисляем индекс цвета
			            $return_color = $this->_get_image_color(
			                            $return_img, $avg_red, $avg_green, $avg_blue );

			            # если же не получиться выбрать цвет, то просто возьмем
			            # копию исходного пикселя
			        } else { $return_color = imagecolorat( $main_img_obj, $x, $y ); }
			        # из полученных пикселей рисуем новое изображение
			        imagesetpixel($return_img, $x, $y, $return_color );
			    }
			}

		    return $return_img;

		} # конец функции create_watermark()

		# усреднение двух цветов с учетом прозрачности альфа-канала
		function _get_ave_color( $color_a, $color_b, $alpha_level )
		{
		    return round((($color_a*(1-$alpha_level))+($color_b*$alpha_level)));
		}
		# возвращаем значения ближайших RGB-составляющих нового рисунка
		function _get_image_color($im, $r, $g, $b)
		{
		    $c=imagecolorexact($im, $r, $g, $b);
		    if ($c!=-1) return $c;
		    $c=imagecolorallocate($im, $r, $g, $b);
		    if ($c!=-1) return $c;
		    return imagecolorclosest($im, $r, $g, $b);
		}





}

?>