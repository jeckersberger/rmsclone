package com.rms.scanner.update

import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.Environment
import android.util.Log
import androidx.core.content.FileProvider
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import com.rms.scanner.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.net.HttpURLConnection
import java.net.URL

/**
 * AppUpdateManager — Checks GitHub Releases for new app versions and handles APK download/install.
 *
 * Flow:
 * 1. On app start (or manual check), calls GitHub API: GET /repos/{owner}/{repo}/releases/latest
 * 2. Compares the release tag (e.g. "v1.2.0") with BuildConfig.VERSION_NAME
 * 3. If newer, shows update dialog with release notes
 * 4. Downloads APK via DownloadManager
 * 5. Triggers install via Intent
 *
 * Release naming convention:
 *   Tag: v1.2.0
 *   Asset: rms-scanner-v1.2.0.apk
 */
class AppUpdateManager(private val context: Context) {
    private val tag = "AppUpdateManager"
    private val githubRepo = BuildConfig.GITHUB_REPO

    data class GitHubRelease(
        @SerializedName("tag_name") val tagName: String,
        @SerializedName("name") val name: String,
        @SerializedName("body") val body: String?,
        @SerializedName("html_url") val htmlUrl: String,
        @SerializedName("assets") val assets: List<GitHubAsset>
    )

    data class GitHubAsset(
        @SerializedName("name") val name: String,
        @SerializedName("browser_download_url") val downloadUrl: String,
        @SerializedName("size") val size: Long
    )

    data class UpdateInfo(
        val versionName: String,
        val releaseNotes: String,
        val downloadUrl: String,
        val apkSize: Long
    )

    /**
     * Check GitHub for a newer release.
     * Returns UpdateInfo if a newer version exists, null otherwise.
     */
    suspend fun checkForUpdate(): UpdateInfo? = withContext(Dispatchers.IO) {
        try {
            val url = URL("https://api.github.com/repos/$githubRepo/releases/latest")
            val connection = url.openConnection() as HttpURLConnection
            connection.requestMethod = "GET"
            connection.setRequestProperty("Accept", "application/vnd.github.v3+json")
            connection.setRequestProperty("User-Agent", "RMS-Scanner/${BuildConfig.VERSION_NAME}")
            connection.connectTimeout = 10000
            connection.readTimeout = 10000

            if (connection.responseCode != 200) {
                Log.w(tag, "GitHub API returned ${connection.responseCode}")
                return@withContext null
            }

            val responseBody = connection.inputStream.bufferedReader().readText()
            val release = Gson().fromJson(responseBody, GitHubRelease::class.java)

            val remoteVersion = release.tagName.removePrefix("v")
            val localVersion = BuildConfig.VERSION_NAME

            if (!isNewerVersion(remoteVersion, localVersion)) {
                Log.d(tag, "App is up to date ($localVersion >= $remoteVersion)")
                return@withContext null
            }

            // Find APK asset
            val apkAsset = release.assets.firstOrNull { it.name.endsWith(".apk") }
            if (apkAsset == null) {
                Log.w(tag, "No APK asset found in release ${release.tagName}")
                return@withContext null
            }

            Log.d(tag, "Update available: $localVersion -> $remoteVersion")
            UpdateInfo(
                versionName = remoteVersion,
                releaseNotes = release.body ?: "Neues Update verfuegbar",
                downloadUrl = apkAsset.downloadUrl,
                apkSize = apkAsset.size
            )
        } catch (e: Exception) {
            Log.e(tag, "Failed to check for updates", e)
            null
        }
    }

    /**
     * Download the APK via Android DownloadManager and trigger install when complete.
     */
    fun downloadAndInstall(updateInfo: UpdateInfo) {
        try {
            val fileName = "rms-scanner-v${updateInfo.versionName}.apk"

            // Clean up old APKs
            val downloadsDir = context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS)
            downloadsDir?.listFiles()?.filter { it.name.endsWith(".apk") }?.forEach { it.delete() }

            val request = DownloadManager.Request(Uri.parse(updateInfo.downloadUrl))
                .setTitle("RMS Scanner Update")
                .setDescription("Version ${updateInfo.versionName} wird heruntergeladen...")
                .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                .setDestinationInExternalFilesDir(context, Environment.DIRECTORY_DOWNLOADS, fileName)
                .setMimeType("application/vnd.android.package-archive")

            val downloadManager = context.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
            val downloadId = downloadManager.enqueue(request)

            Log.d(tag, "Download started: $fileName (ID: $downloadId)")

            // Register receiver for download completion
            val receiver = object : BroadcastReceiver() {
                override fun onReceive(ctx: Context?, intent: Intent?) {
                    val id = intent?.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1)
                    if (id == downloadId) {
                        Log.d(tag, "Download complete, triggering install")
                        installApk(fileName)
                        try {
                            context.unregisterReceiver(this)
                        } catch (_: Exception) {}
                    }
                }
            }
            context.registerReceiver(receiver, IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE))

        } catch (e: Exception) {
            Log.e(tag, "Failed to start download", e)
        }
    }

    /**
     * Open the downloaded APK for installation.
     */
    private fun installApk(fileName: String) {
        try {
            val file = File(context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), fileName)
            if (!file.exists()) {
                Log.e(tag, "APK file not found: $fileName")
                return
            }

            val uri = FileProvider.getUriForFile(
                context,
                "${context.packageName}.fileprovider",
                file
            )

            val installIntent = Intent(Intent.ACTION_VIEW).apply {
                setDataAndType(uri, "application/vnd.android.package-archive")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
            }
            context.startActivity(installIntent)
        } catch (e: Exception) {
            Log.e(tag, "Failed to install APK", e)
        }
    }

    /**
     * Compare two SemVer version strings.
     * Returns true if remote is newer than local.
     */
    private fun isNewerVersion(remote: String, local: String): Boolean {
        try {
            val remoteParts = remote.split(".").map { it.toInt() }
            val localParts = local.split(".").map { it.toInt() }

            for (i in 0 until maxOf(remoteParts.size, localParts.size)) {
                val r = remoteParts.getOrElse(i) { 0 }
                val l = localParts.getOrElse(i) { 0 }
                if (r > l) return true
                if (r < l) return false
            }
            return false
        } catch (e: Exception) {
            Log.e(tag, "Version comparison failed: $remote vs $local", e)
            return false
        }
    }
}
