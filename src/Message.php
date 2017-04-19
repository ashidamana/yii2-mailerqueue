<?php

namespace flyerangel\mailerqueue;

use Yii;
/**
 * http://www.yiiframework.com/doc-2.0/yii-swiftmailer-message.html
 */
class Message extends \yii\swiftmailer\Message {

    public function queue() {
        $redis = Yii::$app->redis;
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config.');
        }
        // 0 - 15  select 0 select 1
        // db => 1
        $mailer = Yii::$app->mailerQueue;
        if (empty($mailer) || !$redis->select($mailer->db)) {
            throw new \yii\base\InvalidConfigException('db not defined.');
        }
        $message = [];
        $message['from'] = $this->getFrom();
        $message['to'] = array_keys($this->getTo());
        $message['cc'] = array_keys(empty($this->getCc()) ? [] : $this->getCc());
        $message['bcc'] = array_keys(empty($this->getBcc()) ? [] : $this->getBcc());
        $message['reply_to'] =  $this->getReplyTo();
        $message['charset'] = $this->getCharset();
        $message['subject'] = $this->getSubject();
        $parts = $this->getSwiftMessage()->getChildren();
        if (!is_array($parts) || !sizeof($parts)) {
            $parts = [$this->getSwiftMessage()];
        }

        foreach ($parts as $part) {
            if (!$part instanceof \Swift_Mime_Attachment) {
                switch ($part->getContentType()) {
                    case 'text/html':
                        $message['html_body'] = $part->getBody();
                        break;
                    case 'text/plain':
                        $message['text_body'] = $part->getBody();
                        break;
                }
                if (!$message['charset']) {
                    $message['charset'] = $part->getCharset();
                }
            }
        }
        return $redis->rpush($mailer->key, json_encode($message));
    }

}
