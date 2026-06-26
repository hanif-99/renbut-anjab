<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Core\Controller;

use OrangeHRM\Framework\Http\JsonResponse;
use OrangeHRM\Framework\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class I18NController extends AbstractController
{
    /**
     * Get language messages for i18n
     *
     * @param Request $request
     * @return Response
     */
    public function getLanguageMessages(Request $request): Response
    {
        // Default locale adalah 'id' (Indonesia)
        $locale = 'id';
        
        // Ambil file localization JSON
        $localizationFile = $this->getLocalizationFilePath($locale);
        
        if (!file_exists($localizationFile)) {
            // Jika file tidak ada, gunakan English sebagai fallback
            $localizationFile = $this->getLocalizationFilePath('en');
        }
        
        if (!file_exists($localizationFile)) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }
        
        // Baca file JSON
        $jsonContent = file_get_contents($localizationFile);
        $messages = json_decode($jsonContent, true);
        
        // Transform format: dari nested JSON menjadi format yang diharapkan frontend
        $transformedMessages = $this->transformMessages($messages, $locale);
        
        return new JsonResponse($transformedMessages);
    }
    
    /**
     * Get localization file path
     *
     * @param string $locale Contoh: 'en', 'id', 'fr'
     * @return string
     */
    private function getLocalizationFilePath(string $locale): string
    {
        // Get the base directory of the application
        $baseDir = dirname(dirname(dirname(dirname(__DIR__))));
        $localesDir = $baseDir . '/client/src/core/plugins/i18n/locales';
        
        return $localesDir . '/' . $locale . '.json';
    }
    
    /**
     * Transform nested JSON menjadi format key.subkey => {source, target}
     *
     * @param array $messages
     * @param string $locale
     * @param string $prefix
     * @return array
     */
    private function transformMessages(array $messages, string $locale, string $prefix = ''): array
    {
        $result = [];
        
        // Baca English sebagai source
        $sourceMessages = [];
        $sourceFile = $this->getLocalizationFilePath('en');
        if (file_exists($sourceFile) && $locale !== 'en') {
            $sourceMessages = json_decode(file_get_contents($sourceFile), true);
        }
        
        foreach ($messages as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                // Jika value adalah array, rekursi
                $result = array_merge(
                    $result,
                    $this->transformMessages($value, $locale, $fullKey)
                );
            } else {
                // Jika value adalah string, tambahkan ke result
                $sourceValue = $this->getNestedValue($sourceMessages, $fullKey);
                $result[$fullKey] = [
                    'source' => $sourceValue ?? $value,
                    'target' => $value,
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Ambil nilai dari nested array menggunakan dot notation
     *
     * @param array $array
     * @param string $key
     * @return string|null
     */
    private function getNestedValue(array $array, string $key): ?string
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return is_string($value) ? $value : null;
    }
}