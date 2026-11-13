# CRRG 终端 · 打包说明

## 这是什么
一个 **WebView 壳**：打开后加载线上站点 `http://crrg-moebius.com`。
**内容不做任何离线打包** —— 网页一改，APP 下次打开就是新的（满足"更新要同步"的要求）。

## 一、最省事：用 Android Studio 直接出 APK
1. 装 [Android Studio](https://developer.android.com/studio)（自带 JDK 与 SDK），首次启动让它装 SDK 34。
2. 打开本目录下的 `android/` 工程（Open → 选 `android` 文件夹）。
3. 菜单 **Build → Build APK(s)**；产物在
   `android/app/build/outputs/apk/debug/app-debug.apk`
4. 把这个 APK 装到手机（允许"未知来源"）即可。
   · debug 包自带调试签名，能装能用；要上架/长期用再配 release 签名。

## 二、命令行出 APK（已装 SDK 的机器）
```bash
cd android
./gradlew assembleDebug      # 产物同上
# 若要发布版（需先配签名）：
./gradlew assembleRelease
```

## 三、正式签名（可选）
```bash
keytool -genkey -v -keystore crrg.keystore -alias crrg -keyalg RSA -keysize 2048 -validity 3650
```
然后在 `app/build.gradle` 的 `buildTypes.release` 里加：
```gradle
signingConfigs { release { storeFile file("../../crrg.keystore"); storePassword "…"; keyAlias "crrg"; keyPassword "…" } }
buildTypes { release { signingConfig signingConfigs.release } }
```

## 四、改地址 / 改名字
- 站点地址：`app/src/main/java/com/crrg/terminal/MainActivity.java` 里的 `START_URL`
- 应用名：`app/src/main/res/values/strings.xml` 的 `app_name`
- 图标：替换 `app/src/main/res/mipmap-*/ic_launcher.png`（同目录另有 192/512 的源图）

## 五、PWA（另一条路，不用装 Android Studio）
同目录的 `manifest.webmanifest`、`sw.js`、`icon-*.png`、`offline.html` 是为浏览器"添加到主屏幕"准备的。
- 现在站点是 **http**：可以"添加到主屏幕"，但 **Service Worker 不会启用**（浏览器要求 HTTPS），所以离线页不生效——不影响在线使用。
- 一旦站上 HTTPS，这套文件自动生效：桌面图标 + 全屏 + 断网时显示离线页。
- 文件部署：把 `app-shell/` 放到站点根（已经放好了），并在主题 `header.php` 里加：
```html
<link rel="manifest" href="/app-shell/manifest.webmanifest">
<meta name="theme-color" content="#0a0a0c">
<script>if('serviceWorker' in navigator){addEventListener('load',()=>navigator.serviceWorker.register('/app-shell/sw.js'));}</script>
```

## 六、为什么这里不直接给你 APK
这台服务器上有 Java，但**没有 javac / gradle / Android SDK / build-tools**，也没有签名工具；
装整套 Android 构建链要 1–2 GB，且它是站点本体所在的生产机 —— 不划算也不安全。
所以：**工程我准备齐（含图标）**，你在本地 Android Studio 里点一下就有 APK；或者我另给一份"在线打包"的最短流程。
