<?php

class HttpXResponse
{
    public int $statusCode;
    public string $body;
    public int $retryCount;

    public function __construct(int $statusCode, string $body, int $retryCount)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->retryCount = $retryCount;
    }
}

class HttpX
{
    private static $proxy = null;
    private static $proxyAuth = null;
    private static $timeout = 30;
    private static $retryCount = 3;
    private static $logFile = null;

    public static function setProxy($proxy, $proxyAuth = null): void
    {
        self::$proxy = $proxy;
        self::$proxyAuth = $proxyAuth;
    }

    public static function setTimeout(int $timeout): void
    {
        self::$timeout = $timeout;
    }

    public static function setRetryCount(int $retryCount): void
    {
        self::$retryCount = $retryCount;
    }

    public static function setLogFile(string $logFile): void
    {
        self::$logFile = $logFile;
    }

    private static function log($message): void
    {
        if (self::$logFile) {
            file_put_contents(self::$logFile, $message . PHP_EOL, FILE_APPEND);
        }
    }

    public static function send($url, $method = 'GET', $data = null, $headers = [], $cookieFile = null): HttpXResponse
    {
        $attempt = 0;

        while ($attempt < self::$retryCount) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => self::$timeout,
                CURLOPT_CONNECTTIMEOUT => self::$timeout,
            ];

            if (self::$proxy) {
                $options[CURLOPT_PROXY] = self::$proxy;
                if (self::$proxyAuth) {
                    $options[CURLOPT_PROXYUSERPWD] = self::$proxyAuth;
                }
            }

            if ($cookieFile !== null) {
                $options[CURLOPT_COOKIEFILE] = $cookieFile;
                $options[CURLOPT_COOKIEJAR] = $cookieFile;
            }

            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                if (is_array($data)) {
                    $options[CURLOPT_POSTFIELDS] = http_build_query($data);
                } else {
                    $options[CURLOPT_POSTFIELDS] = $data;
                }
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                self::log('Client Error - ' . curl_error($ch));
                curl_close($ch);
                $attempt++;
                continue;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($httpCode == 429) { 
                sleep(1);
                $attempt++;
                continue;
            }

            $response = new HttpXResponse($httpCode, $response, $attempt);
            self::log('Request to ' . $url . ' with method ' . $method . ' returned status ' . $httpCode);
            self::log('Response: ' . $response->body);
            self::log('Retry Count: ' . $attempt);

            return $response;
        }

        throw new Exception('Max retry attempts reached');
    }

    public static function sendJson($url, $method = 'GET', $data = null, $headers = [], $cookieFile = null): HttpXResponse
    {
        $headers[] = 'Content-Type: application/json';

        if (is_array($data)) {
            $data = json_encode($data);
        }

        return self::send($url, $method, $data, $headers, $cookieFile);
    }

    public static function multiSend($requests): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];
        $responses = [];

        foreach ($requests as $key => $request) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $request['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $request['method'] ?? 'GET',
                CURLOPT_HTTPHEADER => $request['headers'] ?? [],
                CURLOPT_TIMEOUT => self::$timeout,
                CURLOPT_CONNECTTIMEOUT => self::$timeout,
            ];

            if (self::$proxy) {
                $options[CURLOPT_PROXY] = self::$proxy;
                if (self::$proxyAuth) {
                    $options[CURLOPT_PROXYUSERPWD] = self::$proxyAuth;
                }
            }

            if (in_array($request['method'], ['POST', 'PUT', 'PATCH']) && isset($request['data'])) {
                if (is_array($request['data'])) {
                    $options[CURLOPT_POSTFIELDS] = http_build_query($request['data']);
                } else {
                    $options[CURLOPT_POSTFIELDS] = $request['data'];
                }
            }

            curl_setopt_array($ch, $options);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        foreach ($handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responses[$key] = new HttpXResponse($httpCode, $response, 0); // No retry count for multiSend
            curl_multi_remove_handle($multiHandle, $ch);
        }

        curl_multi_close($multiHandle);

        return $responses;
    }
}
