<?php

namespace app\models;

class Kafaka
{
	public $broker_list = 'ip:9092';
	public $topic = "topic";
	public $partition = 0;
	public $logFile = '@app/runtime/logs/kafka/info.log';

	// Kafka可以让合适的数据以合适的形式出现在合适的地方。Kafka的做法是提供消息队列，让生产者单往队列的末尾添加数据，让多个消费者从队列里面依次读取数据然后自行处理。之前连接的复杂度是O(N^2)，而现在降低到O(N)，扩展起来方便多了：
	protected $producer = null;
	protected $consumer = null;

	public function __construct()
	{
		if (empty($this->broker_list)) {
			throw new \yii\base\InvalidConfigException("broker not config");
		}
		$rk = new \RdKafka\Producer();
		if (empty($rk)) {
			throw new \yii\base\InvalidConfigException("producer error");
		}
		$rk->setLogLevel(LOG_DEBUG);
		if (!$rk->addBrokers($this->broker_list)) {
			throw new \yii\base\InvalidConfigException("producer error");
		}
		$this->producer = $rk;
	}

	public function send($messages = [])
	{
		$topic = $this->producer->newTopic($this->topic);
		return $topic->produce(RD_KAFKA_PARTITION_UA, $this->partition, json_encode($messages));
	}

	public function consumer($object, $callback)
	{
		$conf = new \RdKafka\Conf();
		$conf->set('group.id',0);
		$conf->set('metadat.broker.list', $this->broker_list);

		$topicConf = new \RdKafka\TopicConf();
		$topicConf->set('auto.offset.reset', 'smallest');

		$conf->setDefaultTopicConf($topicConf);

		$consumer = new \RdKafka\KafkaConsumer($conf);

		$consumer->subscribe([$this->topic]);

		echo "waiting for messages......\n";
		while(true) {
			$message = $consumer->consume(120*1000);
			switch ($message->err) {
				case RD_KAFKA_RESP_ERR_NO_ERROR:
					echo "message payload....";
					$object->$callback($message->payload);
					break;
			}
			sleep(1);
		}
	}
}
