<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Slack;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;

/**
 * Slack record utility helping to log to Slack webhooks or API.
 *
 * @author Greg Kedzierski <greg@gregkedzierski.com>
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 * @see    https://api.slack.com/docs/message-attachments
 */
class SlackRecord
{
    /**
     * Slack channel (encoded ID or name)
     * @var string
     */
    private $channel;

    /**
     * Name of a bot
     * @var string
     */
    private $username;

    /**
     * Emoji icon name
     * @var string
     */
    private $iconEmoji;

    /**
     * Whether the message should be added to Slack as attachment (plain text otherwise)
     * @var bool
     */
    private $useAttachment;

    /**
     * Whether the the context/extra messages added to Slack as attachments are in a short style
     * @var bool
     */
    private $useShortAttachment;

    /**
     * Whether the attachment should include context and extra data
     * @var bool
     */
    private $includeContextAndExtra;

    /**
     * @var FormatterInterface
     */
    private $formatter;

    /**
     * @var LineFormatter
     */
    private $lineFormatter;

    public function __construct(
        $channel,
        $username = 'Monolog',
        $useAttachment = true,
        $iconEmoji = null,
        $useShortAttachment = false,
        $includeContextAndExtra = false,
        FormatterInterface $formatter = null
    ) {
        $this->channel = $channel;
        $this->username = $username;
        $this->iconEmoji = trim($iconEmoji, ':');
        $this->useAttachment = $useAttachment;
        $this->useShortAttachment = $useShortAttachment;
        $this->includeContextAndExtra = $includeContextAndExtra;
        $this->formatter = $formatter;

        if ($this->includeContextAndExtra && $this->useShortAttachment) {
            $this->lineFormatter = new LineFormatter();
        }
    }

    public function getSlackData(array $record)
    {
        $dataArray = array(
            'username'    => $this->username,
            'text'        => '',
            'attachments' => array(),
        );

        if ($this->channel) {
            $dataArray['channel'] = $this->channel;
        }

        if ($this->formatter) {
            $message = $this->formatter->format($record);
        } else {
            $message = $record['message'];
        }

        if ($this->useAttachment) {
            $attachment = array(
                'fallback' => $message,
                'color'    => $this->getAttachmentColor($record['level']),
                'fields'   => array(),
            );

            if ($this->useShortAttachment) {
                $attachment['title'] = $record['level_name'];
                $attachment['text'] = $message;
            } else {
                $attachment['title'] = 'Message';
                $attachment['text'] = $message;
                $attachment['fields'][] = array(
                    'title' => 'Level',
                    'value' => $record['level_name'],
                    'short' => true,
                );
            }

            if ($this->includeContextAndExtra) {
                if (!empty($record['extra'])) {
                    if ($this->useShortAttachment) {
                        $attachment['fields'][] = array(
                            'title' => "Extra",
                            'value' => $this->stringify($record['extra']),
                            'short' => $this->useShortAttachment,
                        );
                    } else {
                        // Add all extra fields as individual fields in attachment
                        foreach ($record['extra'] as $var => $val) {
                            $attachment['fields'][] = array(
                                'title' => $var,
                                'value' => $val,
                                'short' => $this->useShortAttachment,
                            );
                        }
                    }
                }

                if (!empty($record['context'])) {
                    if ($this->useShortAttachment) {
                        $attachment['fields'][] = array(
                            'title' => "Context",
                            'value' => $this->stringify($record['context']),
                            'short' => $this->useShortAttachment,
                        );
                    } else {
                        // Add all context fields as individual fields in attachment
                        foreach ($record['context'] as $var => $val) {
                            $attachment['fields'][] = array(
                                'title' => $var,
                                'value' => $val,
                                'short' => $this->useShortAttachment,
                            );
                        }
                    }
                }
            }

            $dataArray['attachments'] = json_encode(array($attachment));
        } else {
            $dataArray['text'] = $message;
        }

        if ($this->iconEmoji) {
            $dataArray['icon_emoji'] = ":{$this->iconEmoji}:";
        }

        return $dataArray;
    }

    /**
     * Returned a Slack message attachment color associated with
     * provided level.
     *
     * @param  int    $level
     * @return string
     */
    public function getAttachmentColor($level)
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return 'danger';
            case $level >= Logger::WARNING:
                return 'warning';
            case $level >= Logger::INFO:
                return 'good';
            default:
                return '#e3e4e6';
        }
    }

    /**
     * Stringifies an array of key/value pairs to be used in attachment fields
     *
     * @param  array  $fields
     * @return string
     */
    public function stringify($fields)
    {
        $string = '';
        foreach ($fields as $var => $val) {
            $string .= $var.': '.$this->lineFormatter->stringify($val)." | ";
        }

        $string = rtrim($string, " |");

        return $string;
    }
}
