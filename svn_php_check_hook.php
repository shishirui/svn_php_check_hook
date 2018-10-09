<?php

class svnCheck
{
    private $repoName       = '';
    private $trxNum         = '';
    private $commitFiles    = [ ];
    private $illegalInfo    = [ 'http://test' ,'https://test' ];
    private $tempPhpPath    = '/tmp/svn_temp.php';

    public function __construct($repoName, $trxNum)
    {
        $this->repoName = $repoName;
        $this->trxNum = $trxNum;
    }

    public function runCheck()
    {
        $commitErrMsg = $this->checkCommitMessage();
        $runCheckMsg = $this->checkFile();

        $messageList = array_merge($commitErrMsg, $runCheckMsg);

        if ($messageList) {
            $result = "\n";
            foreach ($messageList as $key => $message) {
                $index = $key + 1;
                $message = trim($message);
                $result .= "[$index] $message\n";
            }
            return $result;
        }

        return '';
    }

    private function getCommitMessage()
    {
        exec("svnlook log -t $this->trxNum $this->repoName", $mess);
        return implode("\n", $mess);
    }

    private function getCommitFiles()
    {
        $command = "svnlook changed $this->repoName --transaction $this->trxNum";
        exec($command, $changed);

        $commitedFiles = array();
        foreach ($changed as $line){
            if (in_array(substr($line,0,1), array('A', 'U'))){
                $filename = substr($line,4);
                unset($content);
                exec("svnlook cat $this->repoName $filename -t $this->trxNum", $content);
                $commitedFiles[$filename] = $content;
            }
        }

        return $commitedFiles;
    }

    private function checkCommitMessage()
    {
        $messageList = [];

        $commitMessage = trim($this->getCommitMessage());
        if (!$commitMessage) {
            $messageList[] = 'Please provide commit comment';
        } else if (strlen($commitMessage) < 10) {
            $messageList[] = 'Commit comment need more than 10 chars';
        }

        return $messageList;
    }

    private function checkFile()
    {
        $messageList = [];

        $this->commitFiles = $this->getCommitFiles();
        if($this->commitFiles){
            foreach($this->commitFiles as $fileName => $fileContent){

                //检查文件类型
                $position = strrpos($fileName,'.');
                $suffix = substr($fileName,$position + 1);

                //检查文件编码
                $fileContent = implode("\r\n",$fileContent);
                if(!$this->checkFileEncoding($fileContent)){
                    $messageList[] = $fileName.'文件编码不正确,只允许提交 UTF-8 编码的文件';
                }

                // 检查文件内容
                $illegal = $this->checkFileContent($fileContent);
                if($illegal){
                    $messageList[] = $fileName.'文件中包含 ' . $illegal . ' 等内容，不允许提交';
                }

                // 检查语法
                if ($suffix == 'php'){
                    $illegal = $this->checkFileSyntax($fileContent, $fileName);
                    if ($illegal){
                        $messageList[] = $illegal;
                    }
                }
            }
        }

        return $messageList;
    }

    private function checkFileEncoding($fileContent)
    {
        $temp = mb_convert_encoding($fileContent,'UTF-8','UTF-8');
        if( md5($fileContent) != md5($temp)){
            return false;
        }
        return true;
    }

    private function checkFileContent($fileContent)
    {
        if($this->illegalInfo){
            foreach($this->illegalInfo as $illegal){
                 if(strpos($fileContent, $illegal) !== false){
                     return $illegal;
                 }
            }
        }

        return '';
    }

    private function checkFileSyntax($fileContent, $filename = '')
    {
        if(!file_put_contents($this->tempPhpPath, $fileContent)){
            return 'php 临时文件写入失败,请联系管理员';
        }

        $cmd = ' php -l '.$this->tempPhpPath;
        exec($cmd, $output);
        $output = join("\n", $output);
        $output = trim(str_replace($this->tempPhpPath, $filename, $output));

        if (!preg_match('/No syntax errors/', $output)) {
            return "\nPHP Syntax Check Failed: $filename\n$output";
        }

        return '';
    }

}

//获取指定参数
if (count($argv) < 2) {
    throw new Exception("参数缺失");
}

$svnCheck = new svnCheck($argv[1], $argv[2]);
$svnInfo  = $svnCheck->runCheck();

if(!$svnInfo){
    exit(0);
} else {
    $stdErr = fopen('php://stderr', 'w');
    fwrite($stdErr, $svnInfo);
    exit(1);
}
