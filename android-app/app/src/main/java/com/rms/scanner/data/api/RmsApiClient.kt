package com.rms.scanner.data.api

import android.util.Log
import com.google.gson.GsonBuilder
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object RmsApiClient {
    private const val TAG = "RmsApiClient"
    private var baseUrl: String = "http://localhost/"
    private var apiService: RmsApiService? = null

    fun setBaseUrl(url: String) {
        baseUrl = if (url.endsWith("/")) url else "$url/"
        apiService = null  // Reset to force recreation with new URL
    }

    fun getBaseUrl(): String = baseUrl

    fun getApiService(): RmsApiService {
        if (apiService == null) {
            apiService = createRetrofit().create(RmsApiService::class.java)
        }
        return apiService!!
    }

    private fun createRetrofit(): Retrofit {
        Log.d(TAG, "Creating Retrofit with base URL: $baseUrl")

        val httpClient = OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .addInterceptor(createLoggingInterceptor())
            .build()

        val gson = GsonBuilder()
            .setLenient()
            .create()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(httpClient)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
    }

    private fun createLoggingInterceptor(): HttpLoggingInterceptor {
        val logging = HttpLoggingInterceptor { message ->
            Log.d(TAG, message)
        }
        logging.setLevel(HttpLoggingInterceptor.Level.BODY)
        return logging
    }

    fun reset() {
        apiService = null
        baseUrl = "http://localhost/"
    }
}
