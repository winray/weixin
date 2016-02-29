<?php

//define常量赋值函数. 赋予“TOKEN”常量值为“weixin”
define("TOKEN", "weixin");

$wechatObj = new wechatCallbackapiTest();
if (!isset($_GET['echostr'])) {
    $wechatObj->responseMsg();
} else {
    $wechatObj->valid();
}

class wechatCallbackapiTest
{
    /*
      验证服务器地址的有效性
      加密/校验流程如下：
        1. 将token、timestamp、nonce三个参数进行字典序排序
        2. 将三个参数字符串拼接成一个字符串进行sha1加密
        3. 开发者获得加密后的字符串可与signature对比，标识该请求来源于微信
    */
    public function valid()
    {
        $echoStr = $_GET["echostr"];
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if($tmpStr == $signature) {
            echo $echoStr;
            exit;
        }
    }

    public function responseMsg()
    {
        //接收微信公众平台发送过来的用户消息，该消息数据结构为XML
        //注意php对大小写敏感
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

        if (!empty($postStr)) {
            //日志记录
            $this->logger("R ".$postStr);

            //将接收到的XML消息数据载入对象$postObj中。
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

            $RX_TYPE = trim($postObj->MsgType);

            $result = "";

            /*
             判断事件消息：
               event：判断事件并进一步判断是否为用户订阅（关注）"subscribe"
               text：用户回复
             */
            switch ($RX_TYPE)
            {
                case "event":
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
            }
            $this->logger("T ".$result);
            echo $result;
        } else {
            echo "";
            exit;
        }
    }

    private function receiveEvent($object)
    {
        switch ($object->Event)
        {
            case "subscribe":
                $content = "欢迎关注Winray，么么哒！";
                break;
        }
        $result = $this->transmitText($object, $content);
        return $result;
    }

    private function receiveText($object)
    {
        $keyword = trim($object->Content);
        $url = "http://apix.sinaapp.com/weather/?appkey=".$object->ToUserName."&city=".urlencode($keyword); 
        $output = file_get_contents($url);
        $content = json_decode($output, true);

        $result = $this->transmitNews($object, $content);
        return $result;
    }


    //XML信息转换
    private function transmitText($object, $content)
    {
        if (!isset($content) || empty($content)) {
            return "";
        }

        /*
            ToUserName: 接收方帐号（收到的OpenID）
            FromUserName: 开发者微信号
            CreateTime: 消息创建时间 （整型）
            MsgType: text/img/video/voice...
            Content: 回复的消息内容
         */

        $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                    </xml>";
        //使用sprintf这个函数将格式化的数据写入到变量中去
        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }

    private function transmitNews($object, $newsArray)
    {
        if(!is_array($newsArray)) {
            return "";
        }
        $itemTpl = "<item>
                        <Title><![CDATA[%s]]></Title>
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        
                    </item>";
        /*
          舍弃关注每日资讯公众号链接
          <Url><![CDATA[%s]]></Url>
          $item['Url']
        */
        $item_str = "";
        foreach ($newsArray as $item) {
            //使用sprintf这个函数将格式化的数据写入到变量中去
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl']);
        }
        $newsTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[news]]></MsgType>
                        <Content><![CDATA[]]></Content>
                        <ArticleCount>%s</ArticleCount>
                        <Articles>$item_str</Articles>
                    </xml>";

        //使用sprintf这个函数将格式化的数据写入到变量中去
        $result = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }

    private function logger($log_content) {}
}
?>