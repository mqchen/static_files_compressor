<?xml version="1.0" encoding="UTF-8"?>

<!-- 
Example usage:
<xsl:variable name="sfc-js">
    <xsl:call-template name="sfc-urlbuilder">
        <xsl:with-param name="mode" select="'js'"></xsl:with-param>
        <xsl:with-param name="path" select="'js'"></xsl:with-param>
        <xsl:with-param name="files">
            <xsl:text>mootools-1.2.4-core.js,mootools-1.2.4.4-more.js,Event.OuterClick.js,Form.Placeholder.js,designstudier.js</xsl:text>
            <xsl:text>,http://www.google-analytics.com/ga.js</xsl:text>
            <xsl:text>,http://platform.twitter.com/widgets.js</xsl:text>
            <xsl:text>,http://connect.facebook.net/en_US/all.js</xsl:text>
        </xsl:with-param>
        <xsl:with-param name="compress" select="true()"></xsl:with-param>
    </xsl:call-template>
</xsl:variable>

<script type="text/javascript" src="{$workspace}/js/{$sfc-js}"></script>
-->

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:template name="sfc-urlbuilder">
        <!-- Mode: either css, js or plain -->
        <xsl:param name="mode"></xsl:param>
        
        <!-- Path relative to workspace/, eg path=styles -->
        <xsl:param name="path"></xsl:param>
        
        <!-- Comma seperated list of filenames, relative to path -->
        <xsl:param name="files"></xsl:param>
        
        <!-- If it should compress/minify css or js -->
        <xsl:param name="compress" select="false()"></xsl:param>
        
        <!-- Output compress (gzip). Set to false to disable. -->
        <xsl:param name="outputcompress" select="true()"></xsl:param>
        
        <!-- Cache mode, either normal, refresh or flush. -->
        <xsl:param name="cache" select="'normal'"></xsl:param>
        
        <!-- Cache timeout, -1 to use system default, otherwise, seconds. -->
        <xsl:param name="cachetimeout" select="'-1'"></xsl:param>
        
        <!-- Debug mode, on or off. Debug messages gets sent with FirePHP. -->
        <xsl:param name="debug" select="false()"></xsl:param>
        
        <xsl:text>SFC.</xsl:text>
        <xsl:value-of select="$mode"></xsl:value-of>
        <xsl:text>?path=</xsl:text>
        <xsl:value-of select="$path"></xsl:value-of>
        <xsl:text>&amp;files=</xsl:text>
        <xsl:value-of select="$files"></xsl:value-of>
        <xsl:if test="$compress = true()">
            <xsl:text>&amp;compress</xsl:text>
        </xsl:if>
        <xsl:if test="$outputcompress = false()">
            <xsl:text>&amp;outputcompress=0</xsl:text>
        </xsl:if>
        <xsl:if test="$cache != 'normal'">
            <xsl:text>&amp;cache=</xsl:text>
            <xsl:value-of select="$cache"></xsl:value-of>
        </xsl:if>
        <xsl:if test="$cachetimeout &gt; 0">
            <xsl:text>&amp;cachetimeout=</xsl:text>
            <xsl:value-of select="$cachetimeout"></xsl:value-of>
        </xsl:if>
        <xsl:if test="$debug = true()">
            <xsl:text>&amp;debug</xsl:text>
        </xsl:if>
	</xsl:template>
</xsl:stylesheet>