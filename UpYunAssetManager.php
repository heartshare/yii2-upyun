<?php
namespace liao0007\yii2\upyun;

use Yii;
use \yii\web\AssetManager;
use \yii\base\Exception;
use \yii\caching\FileDependency;
use \yii\helpers\FileHelper;

class UpYunAssetManager extends AssetManager
{
    public $path;
    public $cdnComponent = 'upyun';
    public $cacheComponent = 'cache';
    private $_baseUrl;
    private $_basePath;
    private $_published;

    public function getBasePath()
    {
        if ($this->_basePath === null)
        {
            $this->_basePath = $this->path;
        }
        return $this->_basePath;
    }

    public function getBaseUrl()
    {
        if ($this->_baseUrl === null)
        {
            $this->_baseUrl = $this->getCDN()->host .$this->path;
        }
        return $this->_baseUrl;
    }

    private function getCache()
    {
        if (!Yii::$app->{$this->cacheComponent})
            throw new Exception('You need to configure a cache storage or set the variable cacheComponent');

        return Yii::$app->{$this->cacheComponent};
    }

    private function getCDN()
    {
        if (!Yii::$app->{$this->cdnComponent})
            throw new Exception('You need to configure the CDN component or set the variable cdnComponent properly');
        return Yii::$app->{$this->cdnComponent};
    }

    private function getCacheKey($path)
    {
        return $this->hash(Yii::$app->request->serverName).'.'.$path;
    }

    public function publish($path, $hashByName=false, $level=-1, $forceCopy=false)
    {
        Yii::beginProfile(__CLASS__.'/'.__FUNCTION__." : $path");

        if (isset($this->_published[$path])) {
            Yii::endProfile(__CLASS__.'/'.__FUNCTION__." : $path");
            return $this->_published[$path];
        }

        else if (($src = realpath($path)) !== false)
        {
            if (is_file($src))
            {
                $dir = $this->hash($hashByName ? basename($src) : dirname($src).filemtime($src));
                $fileName = basename($src);
                $dstDir = $this->getBasePath().'/'.$dir;
                $dstFile = $dstDir.'/'.$fileName;

                if ($this->getCache()->get($this->getCacheKey($path)) === false)
                {
                    if ($this->getCDN()->saveAs($src, $dstFile, false))
                    {
                        $this->getCache()->set($this->getCacheKey($path), true, 0, new FileDependency($src));
                    }
                    else
                    {
                        throw new Exception('Could not send asset do CDN');
                    }
                }
                Yii::endProfile(__CLASS__.'/'.__FUNCTION__." : $path");
                return $this->_published[$path] = $this->getBaseUrl()."/$dir/$fileName";
            }
            else if (is_dir($src))
            {
                $dir = $this->hash($hashByName ? basename($src) : $src.filemtime($src));
                $dstDir = $this->getBasePath().DIRECTORY_SEPARATOR.$dir;

                if ($this->getCache()->get($this->getCacheKey($path)) === false)
                {
                    $files = FileHelper::findFiles($src, array(
                            'exclude' => $this->excludeFiles,
                            'level' => $level,
                        )
                    );

                    foreach ($files as $f)
                    {
                        $dstFile = $this->getBasePath().'/'.$dir.'/'.str_replace($src.DIRECTORY_SEPARATOR, "", $f);
                        if (!$this->getCDN()->saveAs($f, $dstFile,false))
                        {
                            throw new Exception('Could not send assets do CDN');
                        }
                    }

                    $this->getCache()->set($this->getCacheKey($path), true, 0, new FileDependency($src));
                }

                Yii::endProfile(__CLASS__.'/'.__FUNCTION__." : $path");
                return $this->_published[$path] = $this->getBaseUrl().'/'.$dir;
            }
        }

        throw new Exception(Yii::t('yii', 'The asset "{asset}" to be published does not exist.', array('{asset}' => $path)));



    }

}

?>
