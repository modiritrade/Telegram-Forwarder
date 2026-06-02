<?php

// Constants
$botToken         = "Your_Bot_Token";
$apiBase          = "https://api.telegram.org/bot$botToken/";
$duplicatesFile   = "duplicates.json";  // For source duplicate tracking
$targetDupFile    = "target_duplicates.json";  // For target duplicate tracking

// File paths for logging and saving forwarding rules and message mappings
$logFile          = "bot.log";
$forwardRulesFile = "forward_rules.json";
$messageMapFile   = "message_map.json";

// ----- Duplicate Functions for Source Messages -----
// 
// This function checks for a duplicate message and also stores
// the latest source message id for a given text hash. It returns an array:
// [bool $isDuplicate, string $originalSourceId]
function checkAndUpdateDuplicate($channelId, $text, $messageId) {
    global $duplicatesFile;
    $now = time();
    $dup = [];

    $fp = fopen($duplicatesFile, "c+");
    if ($fp === false) {
        logAction("Failed to open duplicates file.");
        return [false, null];
    }
    if (flock($fp, LOCK_EX)) {
        $fileSize = filesize($duplicatesFile);
        $data = $fileSize > 0 ? fread($fp, $fileSize) : "";
        $dup = $data ? json_decode($data, true) : [];
        if (!is_array($dup)) {
            $dup = [];
        }
        if (!isset($dup[$channelId]) || !is_array($dup[$channelId])) {
            $dup[$channelId] = [];
        } else {
            // Remove expired entries (older than 10 seconds)
            foreach ($dup[$channelId] as $hash => $record) {
                if (($now - $record['timestamp']) >= 10) {
                    unset($dup[$channelId][$hash]);
                }
            }
        }
        $hash = md5($text);
        if (isset($dup[$channelId][$hash]) && (($now - $dup[$channelId][$hash]['timestamp']) < 10)) {
            // Duplicate detected.
            $originalSourceId = $dup[$channelId][$hash]['source_id'];
            // Update with the new (latest) source message id.
            $dup[$channelId][$hash] = ['timestamp' => $now, 'source_id' => $messageId];
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($dup));
            flock($fp, LOCK_UN);
            fclose($fp);
            return [true, $originalSourceId];
        }
        // Not a duplicate—store record.
        $dup[$channelId][$hash] = ['timestamp' => $now, 'source_id' => $messageId];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($dup));
        flock($fp, LOCK_UN);
        fclose($fp);
        return [false, null];
    } else {
        fclose($fp);
        return [false, null];
    }
}

// ----- Logging & API Functions -----

function logAction($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function sendMessage($chatId, $text, $replyToMessageId = null) {
    global $apiBase;
    $url = $apiBase . "sendMessage";
    $payload = ['chat_id' => $chatId, 'text' => $text];
    if ($replyToMessageId) {
        $payload['reply_to_message_id'] = $replyToMessageId;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) {
        logAction("Failed to send message to $chatId: $text");
    } else {
        logAction("Message sent to $chatId: $text");
    }
    return $response;
}

function editMessage($chatId, $text, $messageId) {
    global $apiBase;
    $url = $apiBase . "editMessageText";
    $payload = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) {
        logAction("Failed to edit message in chat $chatId (message_id: $messageId)");
    } else {
        logAction("Edited message in chat $chatId (message_id: $messageId)");
    }
    return $response;
}

function deleteMessage($chatId, $messageId) {
    global $apiBase;
    $url = $apiBase . "deleteMessage";
    $payload = [
        'chat_id'    => $chatId,
        'message_id' => $messageId
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) {
        logAction("Failed to delete message in chat $chatId");
    } else {
        logAction("Message deleted in chat $chatId (message_id: $messageId)");
    }
}

// ----- Duplicate Functions for Target Channel Messages -----

function checkAndStoreTargetDuplicate($targetChatId, $text, $targetMessageId) {
    global $targetDupFile;
    $now = time();
    $dup = [];

    $fp = fopen($targetDupFile, "c+");
    if ($fp === false) {
        logAction("Failed to open target duplicates file.");
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        $fileSize = filesize($targetDupFile);
        $data = $fileSize > 0 ? fread($fp, $fileSize) : "";
        $dup = $data ? json_decode($data, true) : [];
        if (!is_array($dup)) {
            $dup = [];
        }
        if (!isset($dup[$targetChatId]) || !is_array($dup[$targetChatId])) {
            $dup[$targetChatId] = [];
        } else {
            foreach ($dup[$targetChatId] as $hash => $entry) {
                if (($now - $entry['timestamp']) >= 10) {
                    unset($dup[$targetChatId][$hash]);
                }
            }
        }
        $hash = md5($text);
        if (isset($dup[$targetChatId][$hash]) && (($now - $dup[$targetChatId][$hash]['timestamp']) < 10)) {
            logAction("Duplicate message detected in target channel $targetChatId for hash $hash. Deleting duplicate.");
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($dup));
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }
        $dup[$targetChatId][$hash] = ['timestamp' => $now, 'message_id' => $targetMessageId];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($dup));
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    } else {
        fclose($fp);
        return false;
    }
}

// ----- Persistence Functions for Rules & Mapping -----

function loadForwardingRules() {
    global $forwardRulesFile;
    if (file_exists($forwardRulesFile)) {
        return json_decode(file_get_contents($forwardRulesFile), true);
    }
    return [];
}

function saveForwardingRules($rules) {
    global $forwardRulesFile;
    file_put_contents($forwardRulesFile, json_encode($rules));
}

function loadMessageMap() {
    global $messageMapFile;
    if (file_exists($messageMapFile)) {
        return json_decode(file_get_contents($messageMapFile), true);
    }
    return [];
}

function saveMessageMap($messageMap) {
    global $messageMapFile;
    if (file_put_contents($messageMapFile, json_encode($messageMap))) {
        logAction("Message map saved successfully.");
    } else {
        logAction("Failed to save message map.");
    }
}

$forwardRules = loadForwardingRules();
$messageMap   = loadMessageMap();

// ----- Keyword Checking Function -----
// This function returns true if the message text matches at least one of the keywords.
// A keyword can be a simple string, or a combo defined within square brackets
// (e.g. [profit+cancelled]) which requires all components to be present.
function checkKeywords($text, $keywords) {
    foreach ($keywords as $keyword) {
        $keyword = trim($keyword);
        if (strlen($keyword) === 0) {
            continue;
        }
        // Check if it's a combo keyword group (e.g. [profit+cancelled])
        if (substr($keyword, 0, 1) === '[' && substr($keyword, -1) === ']') {
            $combo = substr($keyword, 1, -1); // remove the surrounding brackets
            $parts = explode('+', $combo);
            $allFound = true;
            foreach ($parts as $part) {
                if (stripos($text, trim($part)) === false) {
                    $allFound = false;
                    break;
                }
            }
            if ($allFound) {
                return true;
            }
        } else {
            // Simple keyword match
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }
    }
    return false;
}

// ----- Forwarding Functions -----

/**
 * Process a channel post (new or edited) that is not a reply.
 *
 * In this modified version, if the post is an edit and it has already been forwarded
 * (i.e. a mapping exists), then the edit is ignored.
 */
function processChannelPost($post, $isEdited = false) {
    global $forwardRules, $messageMap;
    $chatId    = $post['chat']['id'];
    $messageId = $post['message_id'];
    $text      = $post['text'] ?? "";

    // Duplicate check (using the original text)
    if ($isEdited && isset($messageMap[$chatId][$messageId]) && !empty($messageMap[$chatId][$messageId])) {
        logAction("Edited message $messageId in chat $chatId already forwarded, ignoring edit.");
        return;
    }

    if (!$isEdited) {
        list($isDuplicate, $originalSourceId) = checkAndUpdateDuplicate($chatId, $text, $messageId);
        if ($isDuplicate) {
            // If a duplicate is detected, update the mapping for this message id
            if (isset($messageMap[$chatId][$originalSourceId])) {
                foreach ($messageMap[$chatId][$originalSourceId] as $ruleIndex => $targetMappings) {
                    // Copy the target mapping from the original message to the duplicate message
                    $messageMap[$chatId][$messageId][$ruleIndex] = $targetMappings;
                }
                logAction("Duplicate message detected in chat $chatId. Updated mapping for duplicate message id $messageId (original: $originalSourceId).");
            }
            saveMessageMap($messageMap);
            return;
        }
    }

    // Prepend source channel name if available
    $channelName = isset($post['chat']['title']) ? $post['chat']['title'] : "";
    $prefixedText = !empty($channelName) ? "[$channelName]\n\n" . $text : $text;

    // For a new (or first-time) post, process all forwarding rules.
    foreach ($forwardRules as $ruleIndex => $rule) {
        if (!is_array($rule) || !isset($rule['sources']) || !isset($rule['targets'])) {
            logAction("Skipping rule at index $ruleIndex: invalid structure.");
            continue;
        }
        if (in_array($chatId, $rule['sources'])) {
            // Check keywords: if set, at least one must match.
            if (!empty($rule['keywords']) && is_array($rule['keywords'])) {
                if (!checkKeywords($text, $rule['keywords'])) {
                    continue;
                }
            }
            // Forward the message to all targets for this rule.
            foreach ($rule['targets'] as $targetChatId) {
                $response = sendMessage($targetChatId, $prefixedText);
                $responseDecoded = json_decode($response, true);
                if (isset($responseDecoded['result']['message_id'])) {
                    $targetMessageId = $responseDecoded['result']['message_id'];
                    $messageMap[$chatId][$messageId][$ruleIndex][$targetChatId] = $targetMessageId;
                    logAction("Forwarded message for rule $ruleIndex from chat $chatId to target $targetChatId");
                }
            }
        }
    }
    saveMessageMap($messageMap);
}

/**
 * Process a channel reply (new or edited).
 *
 * This version also ignores any later edits if the reply has already been forwarded.
 */
function processChannelReply($post, $isEdited = false) {
    global $forwardRules, $messageMap;
    $chatId         = $post['chat']['id'];
    $replyMessageId = $post['message_id'];
    $parentMessageId = $post['reply_to_message']['message_id'];
    $replyText      = $post['text'] ?? "";

    // Duplicate check (using the original reply text)
    if ($isEdited && isset($messageMap[$chatId][$replyMessageId]) && !empty($messageMap[$chatId][$replyMessageId])) {
        logAction("Edited reply message $replyMessageId in chat $chatId already forwarded, ignoring edit.");
        return;
    }

    if (!$isEdited) {
        list($isDuplicate, $originalSourceId) = checkAndUpdateDuplicate($chatId, $replyText, $replyMessageId);
        if ($isDuplicate) {
            // Update mapping for duplicate reply
            if (isset($messageMap[$chatId][$originalSourceId])) {
                foreach ($messageMap[$chatId][$originalSourceId] as $ruleIndex => $targetMappings) {
                    $messageMap[$chatId][$replyMessageId][$ruleIndex] = $targetMappings;
                }
                logAction("Duplicate reply detected in chat $chatId. Updated mapping for duplicate reply id $replyMessageId (original: $originalSourceId).");
            }
            saveMessageMap($messageMap);
            return;
        }
    }

    // Prepend source channel name if available
    $channelName = isset($post['chat']['title']) ? $post['chat']['title'] : "";
    $prefixedReplyText = !empty($channelName) ? "[$channelName]\n\n" . $replyText : $replyText;

    foreach ($forwardRules as $ruleIndex => $rule) {
        if (!is_array($rule) || !isset($rule['sources']) || !isset($rule['targets'])) {
            continue;
        }
        if (in_array($chatId, $rule['sources'])) {
            // Check keywords: require a match if keywords are set.
            if (!empty($rule['keywords']) && is_array($rule['keywords'])) {
                if (!checkKeywords($replyText, $rule['keywords'])) {
                    continue;
                }
            }
            foreach ($rule['targets'] as $targetChatId) {
                // Check if parent's forwarded mapping exists.
                if (!isset($messageMap[$chatId][$parentMessageId][$ruleIndex][$targetChatId])) {
                    // Forward the reply as a new message if the parent hasn't been forwarded.
                    $response = sendMessage($targetChatId, $prefixedReplyText);
                    $responseDecoded = json_decode($response, true);
                    if (isset($responseDecoded['result']['message_id'])) {
                        $targetReplyId = $responseDecoded['result']['message_id'];
                        $messageMap[$chatId][$replyMessageId][$ruleIndex][$targetChatId] = $targetReplyId;
                        logAction("Forwarded reply as new message for rule $ruleIndex from chat $chatId to target $targetChatId (parent not found).");
                    }
                    continue;
                }
                $parentForwardedId = $messageMap[$chatId][$parentMessageId][$ruleIndex][$targetChatId];
                // Forward the reply if it hasn't been done already.
                if (!isset($messageMap[$chatId][$replyMessageId][$ruleIndex][$targetChatId])) {
                    $response = sendMessage($targetChatId, $prefixedReplyText, $parentForwardedId);
                    $responseDecoded = json_decode($response, true);
                    if (isset($responseDecoded['result']['message_id'])) {
                        $targetReplyId = $responseDecoded['result']['message_id'];
                        $messageMap[$chatId][$replyMessageId][$ruleIndex][$targetChatId] = $targetReplyId;
                        logAction("Forwarded reply for rule $ruleIndex from chat $chatId to target $targetChatId");
                    }
                }
            }
        }
    }
    saveMessageMap($messageMap);
}

/**
 * Process deletion of a message in a channel.
 */
function processChannelDeletion($deletedMessage) {
    global $forwardRules, $messageMap;
    $chatId    = $deletedMessage['chat']['id'];
    $messageId = $deletedMessage['message_id'];

    foreach ($forwardRules as $ruleIndex => $rule) {
        if (!is_array($rule) || !isset($rule['sources']) || !isset($rule['targets'])) {
            continue;
        }
        if (in_array($chatId, $rule['sources']) && isset($messageMap[$chatId][$messageId][$ruleIndex])) {
            foreach ($messageMap[$chatId][$messageId][$ruleIndex] as $targetChatId => $targetMessageId) {
                deleteMessage($targetChatId, $targetMessageId);
                logAction("Deleted forwarded message for rule $ruleIndex from chat $chatId in target $targetChatId");
            }
            unset($messageMap[$chatId][$messageId][$ruleIndex]);
        }
    }
    saveMessageMap($messageMap);
}

/**
 * Main update processing function.
 */
function processUpdate() {
    global $forwardRules, $messageMap;
    $update = json_decode(file_get_contents("php://input"), true);

    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId  = $message['chat']['id'];
        $text    = $message['text'] ?? "";
        logAction("Received message from chat $chatId: $text");

        if ($message['chat']['type'] === 'private') {
            if (preg_match('/^\/forward\s+([-\d,\s]+)\s+to\s+([-\d,\s,]+)(?:\s+(.+))?$/', $text, $matches)) {
                $sourcesStr = $matches[1];
                $targetsStr = $matches[2];
                $keywordsStr = isset($matches[3]) ? trim($matches[3]) : "";

                $sources = array_filter(array_map('trim', explode(',', $sourcesStr)), function($val){ return $val !== ""; });
                $targets = array_filter(array_map('trim', explode(',', $targetsStr)), function($val){ return $val !== ""; });

                $keywords = [];
                if ($keywordsStr !== "") {
                    $keywords = array_filter(array_map('trim', explode(',', $keywordsStr)), function($val){ return $val !== ""; });
                }

                $newRule = [
                    'sources'  => array_values($sources),
                    'targets'  => array_values($targets),
                    'keywords' => $keywords
                ];
                $forwardRules[] = $newRule;
                saveForwardingRules($forwardRules);

                $replyText = "Forwarding rule added:\nSources: " . implode(", ", $newRule['sources']) .
                             "\nTargets: " . implode(", ", $newRule['targets']);
                if (!empty($keywords)) {
                    $replyText .= "\nOnly messages containing keyword(s): '" . implode("', '", $keywords) . "' will be forwarded.";
                } else {
                    $replyText .= "\nAll messages will be forwarded.";
                }
                sendMessage($chatId, $replyText);
                logAction("Added forwarding rule: " . json_encode($newRule));
            } else {
                sendMessage($chatId, "Invalid command.\nUse: /forward [source_channel_ids] to [target_channel_ids] [optional_keyword1,optional_keyword2,...]");
            }
        }
    } elseif (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        if (isset($post['reply_to_message'])) {
            processChannelReply($post, false);
        } else {
            processChannelPost($post, false);
        }
    } elseif (isset($update['edited_channel_post'])) {
        $editedPost = $update['edited_channel_post'];
        if (isset($editedPost['reply_to_message'])) {
            processChannelReply($editedPost, true);
        } else {
            processChannelPost($editedPost, true);
        }
    } elseif (isset($update['channel_post_deleted'])) {
        $deletedMessage = $update['channel_post_deleted'];
        processChannelDeletion($deletedMessage);
    }
}

processUpdate();

?>
