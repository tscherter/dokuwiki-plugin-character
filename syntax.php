<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin character (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Vincent Tscherter <vincent@tscherter.net>
 */
class syntax_plugin_character extends SyntaxPlugin {

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'normal';
    }

    public function getSort() {
        return 100;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{c>[^}]*}?}}', $mode, 'plugin_character');
    }

    const ES = [
        0 => ['\\0', 'NULL'],
        8 => ['\\b', 'BACKSPACE'],
        9 => ['\\t', 'CHARACTER TABULATION'],
        10 => ['\\n', 'LINE FEED'],
        11 => ['\\v', 'LINE TABULATION'],
        12 => ['\\f', 'FORM FEED'],
        13 => ['\\r', 'CARRIAGE RETURN'],
        92 => ['\\\\', 'REVERSE SOLIDUS']
    ];
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $data = [];

        $raw = substr($match, 4, -2);
        $data['raw'] = $raw;
    
        // U+0000 to U+10FFFFF Notation
        if (preg_match('/^u\+([0-9a-f]{1,7})$/i', $raw)) {
            $data['type'] = 'codepoint';
            $data['codepoint'] = hexdec(substr($raw, 2));  
        }  else if (preg_match('/^\\\\x[0-9a-f]{2}$/i', $raw)) {
            $data['type'] = 'hex_esc_seq';
            $data['codepoint'] = hexdec(substr($raw, 2));  
        } else if (preg_match('/^\\\\u\{[0-9a-f]{1,6}\}$/i', $raw)) {
            $data['type'] = 'codepoint_escape';
            $data['codepoint'] = hexdec(substr($raw, 3, -1));  
        } else if (preg_match('/^&#[0-9]+;$/i', $raw)) {
            $data['type'] = 'dec_html_entity';
            $data['codepoint'] = intval(substr($raw, 2, -1));  
        } else if (preg_match('/^&#x[0-9a-f]+;$/i', $raw)) {
            $data['type'] = 'hex_html_entity';
            $data['codepoint'] = hexdec(substr($raw, 3, -1));  
        } else if (preg_match('/^(%[0-9a-f]{2}){1,4}/i', $raw)) {
            $data['type'] = 'url_encoding';
            $char = urldecode($raw);
            $data['codepoint'] = mb_ord($char);
            if (mb_strlen($char)!=1) $data['type'] = 'error';
        } else if (mb_strlen($raw) == 1) {
            $data['type'] = 'character_literal';
            $data['codepoint'] = mb_ord($raw);
        } else {
            $data['type'] = 'error'; 
            $data['msg'] = mb_strlen($raw);
            $data['codepoint'] = -1;
            foreach (self::ES as $key => $entry) {
                if ($raw==$entry[0]) {
                    $data['type'] = 'escape_sequence';
                    $data['codepoint'] = $key;
                }
            }
        }
        return $data;
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') return false;
        
        $codepoint = $data['codepoint'];
        if ($data['type']=='error' || $codepoint > 0x10FFFFF) {
            if (empty($data['msg'])) $data['msg'] = '-';
            $renderer->doc .= '<code style="color: red">⚠️ invalid input ['
                . htmlentities($data['raw']) .']: '.$data['msg'].'</code>';
            return true;
        }
         
        $char = mb_chr($codepoint);
        
        $rendered = 'invalid';
        $hex = strtoupper(dechex($codepoint));
        $paddedHex = str_pad($hex, 4, '0', STR_PAD_LEFT);
        $charname  = extension_loaded('intl') ? ' '.IntlChar::charName($codepoint).'' : '';
        $escapesequence = '\u{'.dechex($codepoint).'}';
        if (!empty(self::ES[$codepoint])) {
            //$escapesequence = self::ES[$codepoint][0];
            $charname = ' '.self::ES[$codepoint][1];
        }
        $urlencoding = urlencode($char);
        if ($urlencoding=='+') $urlencoding = '%20';
        
        $orange = ' style="color: goldenrod"';
        $dred =  ' style="color: darkred"';
        $green =  ' style="color: green"';
        $blue =  ' style="color: blue"';

        $bytes = unpack('C*', mb_convert_encoding($char, 'UTF-8', 'UTF-8'));
        $type = $data['type'];

        if ($type=='codepoint') {
            $rendered = "<code$dred><span$orange>U+</span>$paddedHex</code>";
            $tip = "unicode code point";
        } else if ($type=='hex_esc_seq') {
            $p = strtoupper(str_pad(dechex($codepoint), 2, '0', STR_PAD_LEFT));
            $rendered = "<code$dred><span$orange>\x</span>$p</code>";
            $tip = "hexadecimal escape sequence";
        } else if ($type=='codepoint_escape') {
            $p = strtoupper(dechex($codepoint));
            $rendered = "<code$dred><span$orange>\u&#x7B;</span>$p<span$orange>}</span></code>";
            $tip = 'code point escape sequence';
        } else if ($type=='dec_html_entity') {
            $rendered = "<code$blue><span$orange>&#</span>$codepoint<span$orange>;</span></code>";
            $tip = "decimal HTML entity";
        } else if ($type=='hex_html_entity') {
            $rendered = "<code$dred><span$orange>&amp;#x</span>$hex<span$orange>;</span></code>";
            $tip = "hexadecimal HTML entity";
        } else if ($type=='url_encoding') {
            $rendered = "<code$green>".htmlentities($data['raw'])."</code>";
            $tip = "URL encoding";
        } else  if ($type=='character_literal') {
            $rendered = '<code style="white-space: pre; padding: 0 0.25ex; color: green">'.htmlentities($data['raw']).'</code>';
            $tip = 'character literal';
        } else  if ($type=='escape_sequence') {
            $rendered = '<code style="white-space: pre; padding: 0 0.25ex; color: hotpink">'.htmlentities($data['raw']).'</code>';
            $tip = 'escape sequence';
        } 
        
        $renderer->doc .= '<span class="plugin-character"><span class="plugin-character-tooltip" >'
            .'<span style="display:inline-grid; grid-template-columns: auto 1fr auto; grid-gap: 0.5ex">'
            .'<span style="padding-bottom: 0.5ex; border-bottom: 1px solid silver;grid-column: 1 / span 3; text-align: center; font-weight: bold">'
            .$tip.'</span>'
            .'<strong>character</strong><span style="grid-column: 2 / span 2;"><code style="padding: 0 0.5ex;margin-right: 0.5ex;color:green;">'
            .htmlentities($char)."</code> $charname</span>"
            ."<strong>code point</strong><code style='text-align: center; color: blue'>$codepoint</code><span>DEC</span>";
        if ($type != 'escape_sequence'   && !empty(self::ES[$codepoint]))
            $renderer->doc .= '<strong>escape sequence</strong><code style="text-align: center; color: green">'
                .self::ES[$codepoint][0].'</code><span></span>';
        if ($type != 'codepoint_escape')
           $renderer->doc .= "<strong>escape sequence</strong><code style='text-align: center; color: green'>$escapesequence</code><span>HEX</span>";
        if ($type != 'hex_html_entity')
            $renderer->doc .= "<strong>HTML entity</strong><code style='text-align: center; color: green'>&amp;#$codepoint;</code><span>DEC</span>";
        if ($type != 'dec_html_entity')
            $renderer->doc .= "<strong>HTML entity</strong><code style='text-align: center; color: green'>&amp;#x$hex;</code><span>HEX</span>";
        if ($type != 'url_encoding' && ($urlencoding=='+' || strlen($urlencoding)>1)) 
            $renderer->doc .= "<strong>URL encoding</strong><code style='text-align: center; color: green'>$urlencoding</code><span>HEX</span>";
        $renderer->doc .= '<strong>UTF-8 code units</strong><code style="text-align: center; color: purple">'
            .join(' ', array_map(function ($i) { return str_pad(decbin($i), 8, '0', STR_PAD_LEFT); }, $bytes))
            .'</code><span>BIN</span>';
        $renderer->doc .= "</span></span>$rendered</span>";

        return true;
    }

}