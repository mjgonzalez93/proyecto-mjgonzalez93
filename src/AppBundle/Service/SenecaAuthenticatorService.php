<?php
/*
  ÁTICA - Aplicación web para la gestión documental de centros educativos

  Copyright (C) 2015-2017: Luis Ramón López López

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see [http://www.gnu.org/licenses/].
*/

namespace AppBundle\Service;

class SenecaAuthenticatorService
{
    const STATUS_NOT_AVAILABLE = 0;
    const STATUS_USER_AUTHENTICATED = 1;
    const STATUS_WRONG_USER_OR_PASSWORD = 2;
    const STATUS_USER_BLOCKED = 3;

    /** @var string */
    private $url;

    /** @var boolean */
    private $forceSecurity;

    /** @var boolean */
    private $enabled;

    public function __construct($url, $forceSecurity, $enabled)
    {
        $this->url = $url;
        $this->forceSecurity = $forceSecurity;
        $this->enabled = $enabled;
    }

    /**
     * @param string $user
     * @param string $password
     * @return bool
     */
    public function checkUserCredentials($user, $password)
    {
        // devolver error si no está habilitado
        if (false === $this->enabled) {
            return self::STATUS_NOT_AVAILABLE;
        }

        // obtener URL de entrada
        $str = $this->getUrl($this->url, $this->forceSecurity);
        if (!$str) {
            return self::STATUS_NOT_AVAILABLE;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($str);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form')->item(0);
        $hidden = $xpath->query('//input[@name="N_V_"]')->item(0);

        if (!$form || !$hidden) {
            return self::STATUS_NOT_AVAILABLE;
        }

        // enviar datos del formulario
        $postUrl = $form->getAttribute('action');
        $hiddenValue = $hidden->getAttribute('value');

        $fields = array(
            'USUARIO' => urlencode($user),
            'CLAVE' => urlencode($password),
            'N_V_' => urlencode($hiddenValue)
        );

        $str = $this->postToUrl($fields, $postUrl, $this->url, $this->forceSecurity);

        if (!$str) {
            return self::STATUS_NOT_AVAILABLE;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($str);
        $xpath = new \DOMXPath($dom);
        $nav = $xpath->query('//nav');
        $error = $xpath->query('//p[@class="text-danger"]');
        $message = $error->length > 0 ? $error->item(0)->firstChild->nodeValue : '';

        if ($nav->length === 1 && $error->length === 0) {
            $result = self::STATUS_USER_AUTHENTICATED;
        }
        elseif (false !== strpos($message, 'Usuario bloqueado')) {
            $result = self::STATUS_USER_BLOCKED;
        }
        elseif (false !== strpos($message, 'Usuario incorrecto')) {
            $result = self::STATUS_WRONG_USER_OR_PASSWORD;
        }
        else {
            $result = self::STATUS_NOT_AVAILABLE;
        }
        return $result;
    }

    /**
     * Get URL contents
     *
     * @param string $url
     * @param boolean $forceSecurity
     * @return string
     */
    private function getUrl($url, $forceSecurity)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $forceSecurity);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 2);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4");
        $str = curl_exec($curl);
        curl_close($curl);
        return $str === false ? '' : (string) $str;
    }

    /**
     * Gets the content after POSTing into an URL
     *
     * @param array $fields
     * @param string $postUrl
     * @param string $refererUrl
     * @param boolean $forceSecurity
     * @return string
     */
    private function postToUrl($fields, $postUrl, $refererUrl, $forceSecurity)
    {
        $fieldsString = '';
        foreach ($fields as $key => $value) {
            $fieldsString .= $key.'='.$value.'&';
        }
        $fieldsString = rtrim($fieldsString, '&');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $forceSecurity);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_URL, $postUrl);
        curl_setopt($curl, CURLOPT_REFERER, $refererUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4");
        curl_setopt($curl, CURLOPT_POST, count($fields));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fieldsString);
        $str = curl_exec($curl);
        curl_close($curl);
        return $str === false ? '' : (string) $str;
    }
}
