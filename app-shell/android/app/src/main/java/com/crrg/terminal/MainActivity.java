package com.crrg.terminal;

import android.annotation.SuppressLint;
import android.os.Bundle;
import android.view.KeyEvent;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.appcompat.app.AppCompatActivity;

/** 壳：只加载线上站点。站点更新后，APP 下次打开就是新内容（此处不做任何离线静态打包）。 */
public class MainActivity extends AppCompatActivity {
    private static final String START_URL = "http://crrg-moebius.com";
    private WebView web;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        web = new WebView(this);
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);   // 正常缓存策略：内容以线上为准
        web.setWebViewClient(new WebViewClient());
        web.loadUrl(START_URL);
        setContentView(web);
    }

    @Override
    public boolean onKeyDown(int code, KeyEvent e) {
        if (code == KeyEvent.KEYCODE_BACK && web.canGoBack()) { web.goBack(); return true; }
        return super.onKeyDown(code, e);
    }
}
