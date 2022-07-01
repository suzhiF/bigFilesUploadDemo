<?php

class UploadService
{
    private $filepath;
    private $tmpPath;       //PHP文件临时目录
    private $blobNum;       //第几个文件块
    private $totalBlobNum;  //文件块总数
    private $fileName;      //文件名
    private $savePath;      //保存路径
    private $basePath;

    public function __construct($type, $tmpPath, $blobNum, $totalBlobNum, $fileName)
    {
        $this->tmpPath = $tmpPath;
        $this->blobNum = $blobNum;
        $this->totalBlobNum = $totalBlobNum;
        $this->fileName = $fileName;
        $this->savePath = date('Ymd', mb_substr($fileName, 0, 10));
        $this->DataPath = date('Ymd', time());
        $this->filepath = __DIR__ . '/public/uploads/' . $type . '/' . $this->DataPath . '/'; //提前建立 __DIR__ 目录 因为没有创建权限
        $this->basePath = __DIR__ . '/public/uploads/' . $type;

    }

    //判断是否是最后一块，如果是则进行文件合成并且删除文件块
    private function fileMerge()
    {
        if ($this->blobNum == $this->totalBlobNum) {
            $blob = '';
            for ($i = 1; $i <= $this->totalBlobNum; $i++) {
                $blob .= file_get_contents($this->filepath . '/' . $this->fileName . '__' . $i);
            }
            file_put_contents($this->filepath . '/' . $this->fileName, $blob);          // 存在内存超出问题
            $this->deleteFileBlob();
        }
    }

    //删除文件块
    private function deleteFileBlob()
    {
        for ($i = 1; $i <= $this->totalBlobNum; $i++) {
            @unlink($this->filepath . '/' . $this->fileName . '__' . $i);
        }
    }

    //移动文件
    private function moveFile()
    {
        $this->touchDir();
        $filename = $this->filepath . '/' . $this->fileName . '__' . $this->blobNum;
        move_uploaded_file($this->tmpPath, $filename);
    }

    private function appendFile()
    {
        $this->touchDir();
        // 唯一的文件名来标识文件
        $tmp_filename = $this->filepath . '/' . $this->fileName;

        // 如果它是创建文件的第一个块，则追加...
        $out_fp = fopen($tmp_filename, $this->blobNum == 1 ? "wb" : "ab");

        // 读取临时上传文件...
        $in_fp = fopen($this->tmpPath, "rb");

        // 复制被上传的区块，到我们正在上传的文件...
        $bool = false;
        while ($buff = fread($in_fp, 4096)) {
            if(!$bool = fwrite($out_fp, $buff)) {
                unlink($tmp_filename);
                return $bool;
            }
        }
        fclose($out_fp);
        fclose($in_fp);
        return $bool;
    }

    //API返回数据
    public function apiReturn()
    {
        if (!file_exists($this->basePath)) {
            mkdir($this->basePath);
        }
        if(!$str = $this->appendFile()) die(json_encode(['code' => 0, 'msg' => '上传失败', 'data' => []]));

        if ($this->blobNum == $this->totalBlobNum) {
            if (file_exists($this->filepath . '/' . $this->fileName)) {
                $data['code'] = 2;
                $data['msg'] = 'success';
                $data['file_path'] = '/uploads/video/' . $this->DataPath . '/' . $this->fileName;
            }
        } else {
            $data['code'] = 1;
            $data['msg'] = 'waiting for all';
            $data['file_path'] = '';
        }
        header('Content-type: application/json');
        echo json_encode($data);
    }

    //建立上传文件夹
    private function touchDir()
    {
        if (!file_exists($this->filepath)) {
            mkdir($this->filepath);
        }
    }
}

function cut_upload()
{
    //实例化并获取系统变量传参
    $upload = new UploadService('video', $_FILES['file']['tmp_name'], $_POST['blob_num'], $_POST['total_blob_num'], $_POST['file_name']);
    //调用方法，返回结果
    $upload->apiReturn();
}

cut_upload();