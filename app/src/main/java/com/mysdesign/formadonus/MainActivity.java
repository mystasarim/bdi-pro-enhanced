package com.mysdesign.formadonus;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.SharedPreferences;
import android.content.res.ColorStateList;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.speech.tts.TextToSpeech;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

public class MainActivity extends Activity implements TextToSpeech.OnInitListener {

    private static final int BLUE = Color.rgb(20, 87, 217);
    private static final int BG = Color.rgb(246, 248, 252);

    private final String[] exerciseNames = {
            "Yerinde Yürüyüş", "Sandalyeye Squat", "Eğik Şınav",
            "Sırt Çantasıyla Row", "Romanian Deadlift", "Glute Bridge",
            "Plank", "Split Squat", "Omuz Press", "Superman",
            "Side Plank", "Tempolu Yürüyüş", "Yavaş Kegel",
            "Hızlı Kegel", "Reverse Kegel", "Diyafram Nefesi"
    };

    private final String[] exerciseCategories = {
            "Isınma", "Bacak", "Üst Vücut", "Sırt", "Kalça", "Kalça",
            "Core", "Bacak", "Omuz", "Sırt", "Core", "Kondisyon",
            "Pelvik Taban", "Pelvik Taban", "Gevşeme", "Nefes"
    };

    private final String[] exerciseCues = {
            "Dik dur ve hafif tempoda yürü.",
            "Kalçanı geriye ver, dizlerin içe kaçmasın.",
            "Vücudunu düz tut ve kontrollü şekilde alçal.",
            "Sırtını düz tut, dirseklerini geriye çek.",
            "Kalçanı geriye ver ve sırtını düz tut.",
            "Kalçanı yukarı kaldır ve üstte sık.",
            "Karnını sık ve nefesini tutma.",
            "Dengeni koru ve kontrollü şekilde alçal.",
            "Çantayı kontrollü şekilde yukarı it.",
            "Kolları ve üst gövdeyi hafifçe kaldır.",
            "Vücudunu düz bir çizgide tut.",
            "Rahat ama canlı bir tempoda yürü.",
            "Pelvik tabanı nazikçe sık, tut ve tamamen gevşet.",
            "Kısa süreli sık ve tamamen bırak.",
            "Pelvik tabanı gevşet ve aşağı doğru bırak.",
            "Karnını doldurarak nefes al, yavaşça nefes ver."
    };

    private final int[] exerciseSeconds = {
            45, 40, 35, 40, 40, 35, 30, 35, 35, 30, 25, 60, 30, 20, 30, 60
    };

    private TextToSpeech tts;
    private SharedPreferences prefs;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private Runnable timerRunnable;
    private boolean timerRunning = false;
    private int currentSeconds = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences("forma_donus", MODE_PRIVATE);
        tts = new TextToSpeech(this, this);
        showHome();
    }

    @Override
    public void onInit(int status) {
        if (status == TextToSpeech.SUCCESS) {
            tts.setLanguage(new Locale("tr", "TR"));
            tts.setSpeechRate(0.95f);
        }
    }

    private void speak(String text) {
        if (tts != null) {
            tts.speak(text, TextToSpeech.QUEUE_FLUSH, null, "forma_donus_coach");
        }
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private TextView title(String text, int size) {
        TextView view = new TextView(this);
        view.setText(text);
        view.setTextSize(size);
        view.setTextColor(Color.rgb(24, 32, 51));
        view.setPadding(0, dp(6), 0, dp(6));
        return view;
    }

    private Button actionButton(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(Color.WHITE);
        button.setTextSize(16);
        button.setAllCaps(false);
        button.setBackgroundTintList(ColorStateList.valueOf(BLUE));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(54));
        params.setMargins(0, dp(6), 0, dp(6));
        button.setLayoutParams(params);
        return button;
    }

    private LinearLayout card() {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(18), dp(16), dp(18), dp(16));
        card.setBackgroundColor(Color.WHITE);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT);
        params.setMargins(0, dp(7), 0, dp(7));
        card.setLayoutParams(params);
        return card;
    }

    private LinearLayout page(String heading, String subtitle) {
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(18), dp(18), dp(18), dp(28));
        root.setBackgroundColor(BG);
        TextView headingView = title(heading, 30);
        headingView.setTextColor(BLUE);
        root.addView(headingView);
        TextView subtitleView = title(subtitle, 15);
        subtitleView.setTextColor(Color.DKGRAY);
        root.addView(subtitleView);
        return root;
    }

    private void setScrollableContent(LinearLayout content) {
        ScrollView scrollView = new ScrollView(this);
        scrollView.setFillViewport(true);
        scrollView.addView(content);
        setContentView(scrollView);
    }

    private void showHome() {
        stopTimer();
        LinearLayout root = page("Forma Dönüş", "MYS Design • Evde kişisel güçlenme");

        LinearLayout personal = card();
        personal.addView(title("Bana Özel Evde Güçlenme", 22));
        personal.addView(title("Seçtiğin haftalık günlere göre güç, core, kondisyon ve pelvik kontrol hareketlerini uygular.", 15));
        Button start = actionButton("Antrenmana Başla");
        start.setOnClickListener(v -> startWorkout(createPersonalPlan()));
        personal.addView(start);
        root.addView(personal);

        LinearLayout schedule = card();
        schedule.addView(title("Haftalık program", 20));
        String days = prefs.getBoolean("thu", true) ? "Perşembe" : "";
        if (prefs.getBoolean("fri", true)) days += days.isEmpty() ? "Cuma" : " ve Cuma";
        if (days.isEmpty()) days = "Henüz gün seçilmedi";
        schedule.addView(title(days, 18));
        Button edit = actionButton("Programı Düzenle");
        edit.setOnClickListener(v -> showProgram());
        schedule.addView(edit);
        root.addView(schedule);

        LinearLayout tools = card();
        tools.addView(title("Egzersiz ve raporlar", 20));
        Button library = actionButton("Egzersiz Kütüphanesi");
        library.setOnClickListener(v -> showLibrary());
        Button manual = actionButton("Manuel Hareket Seç");
        manual.setOnClickListener(v -> showManualSelection());
        Button report = actionButton("Rapor Ekranı");
        report.setOnClickListener(v -> showReport());
        tools.addView(library);
        tools.addView(manual);
        tools.addView(report);
        root.addView(tools);

        LinearLayout safety = card();
        safety.addView(title("Güvenlik", 19));
        safety.addView(title("Göğüs ağrısı, ciddi nefes darlığı, baş dönmesi veya keskin ağrı olursa antrenmanı durdur.", 15));
        root.addView(safety);

        setScrollableContent(root);
    }

    private void showProgram() {
        LinearLayout root = page("Akıllı Program", "Günlerini ve destek hedefini seç");
        LinearLayout options = card();

        CheckBox thu = new CheckBox(this);
        thu.setText("Perşembe");
        thu.setTextSize(18);
        thu.setChecked(prefs.getBoolean("thu", true));

        CheckBox fri = new CheckBox(this);
        fri.setText("Cuma");
        fri.setTextSize(18);
        fri.setChecked(prefs.getBoolean("fri", true));

        CheckBox pelvic = new CheckBox(this);
        pelvic.setText("Seks kontrolü, pelvik taban ve nefes desteği");
        pelvic.setTextSize(17);
        pelvic.setChecked(prefs.getBoolean("pelvic", true));

        options.addView(thu);
        options.addView(fri);
        options.addView(pelvic);

        Button save = actionButton("Kaydet ve Programı Oluştur");
        save.setOnClickListener(v -> {
            if (!thu.isChecked() && !fri.isChecked()) {
                Toast.makeText(this, "En az bir antrenman günü seçmelisin.", Toast.LENGTH_LONG).show();
                return;
            }
            prefs.edit()
                    .putBoolean("thu", thu.isChecked())
                    .putBoolean("fri", fri.isChecked())
                    .putBoolean("pelvic", pelvic.isChecked())
                    .apply();
            startWorkout(createPersonalPlan());
        });
        options.addView(save);

        Button back = actionButton("Ana Sayfaya Dön");
        back.setOnClickListener(v -> showHome());
        options.addView(back);
        root.addView(options);

        LinearLayout info = card();
        info.addView(title("Otomatik sıralama", 19));
        info.addView(title("Isınma → ana güç → yardımcı güç → core → kondisyon → pelvik taban → nefes", 16));
        root.addView(info);
        setScrollableContent(root);
    }

    private List<Integer> createPersonalPlan() {
        Set<Integer> plan = new LinkedHashSet<>();
        int[] base = {0, 1, 2, 3, 4, 5, 6, 15};
        for (int index : base) plan.add(index);
        if (prefs.getBoolean("pelvic", true)) {
            plan.add(12);
            plan.add(13);
            plan.add(14);
            plan.add(15);
        }
        return new ArrayList<>(plan);
    }

    private void showLibrary() {
        LinearLayout root = page("Egzersizler", "Amaca ve kas grubuna göre hareketler");
        for (int i = 0; i < exerciseNames.length; i++) {
            final int index = i;
            LinearLayout item = card();
            item.addView(title(exerciseNames[i], 19));
            TextView category = title(exerciseCategories[i] + " • " + exerciseSeconds[i] + " saniye", 14);
            category.setTextColor(Color.GRAY);
            item.addView(category);
            item.setOnClickListener(v -> new AlertDialog.Builder(this)
                    .setTitle(exerciseNames[index])
                    .setMessage(exerciseCues[index] + "\n\nKategori: " + exerciseCategories[index]
                            + "\nÖnerilen süre: " + exerciseSeconds[index] + " saniye")
                    .setPositiveButton("Tek Hareketi Başlat", (dialog, which) -> {
                        List<Integer> one = new ArrayList<>();
                        one.add(index);
                        startWorkout(one);
                    })
                    .setNegativeButton("Kapat", null)
                    .show());
            root.addView(item);
        }
        Button back = actionButton("Ana Sayfaya Dön");
        back.setOnClickListener(v -> showHome());
        root.addView(back);
        setScrollableContent(root);
    }

    private void showManualSelection() {
        boolean[] selected = new boolean[exerciseNames.length];
        new AlertDialog.Builder(this)
                .setTitle("Programa eklenecek hareketleri seç")
                .setMultiChoiceItems(exerciseNames, selected, (dialog, which, isChecked) -> selected[which] = isChecked)
                .setPositiveButton("Programı Oluştur", (dialog, which) -> {
                    List<Integer> plan = new ArrayList<>();
                    for (int i = 0; i < selected.length; i++) {
                        if (selected[i]) plan.add(i);
                    }
                    if (plan.isEmpty()) {
                        Toast.makeText(this, "En az bir hareket seçmelisin.", Toast.LENGTH_LONG).show();
                    } else {
                        plan.sort((a, b) -> Integer.compare(orderForCategory(exerciseCategories[a]), orderForCategory(exerciseCategories[b])));
                        startWorkout(plan);
                    }
                })
                .setNegativeButton("İptal", null)
                .show();
    }

    private int orderForCategory(String category) {
        if ("Isınma".equals(category)) return 1;
        if ("Bacak".equals(category) || "Üst Vücut".equals(category)
                || "Sırt".equals(category) || "Kalça".equals(category)
                || "Omuz".equals(category)) return 2;
        if ("Core".equals(category)) return 3;
        if ("Kondisyon".equals(category)) return 4;
        if ("Pelvik Taban".equals(category) || "Gevşeme".equals(category)) return 5;
        return 6;
    }

    private void showReport() {
        LinearLayout root = page("Raporlar", "Gelişimini cihazında takip et");
        LinearLayout summary = card();
        int completed = prefs.getInt("completed", 0);
        summary.addView(title("Tamamlanan antrenman", 19));
        TextView number = title(String.valueOf(completed), 48);
        number.setTextColor(BLUE);
        summary.addView(number);
        summary.addView(title("Takip alanları: kilo, squat/şınav tekrarları, plank süresi, devamlılık ve pelvik nefes çalışmaları.", 15));
        root.addView(summary);

        Button reset = actionButton("Test Raporunu Sıfırla");
        reset.setOnClickListener(v -> {
            prefs.edit().putInt("completed", 0).apply();
            showReport();
        });
        root.addView(reset);
        Button back = actionButton("Ana Sayfaya Dön");
        back.setOnClickListener(v -> showHome());
        root.addView(back);
        setScrollableContent(root);
    }

    private void startWorkout(List<Integer> plan) {
        if (plan == null || plan.isEmpty()) {
            Toast.makeText(this, "Program boş.", Toast.LENGTH_LONG).show();
            return;
        }
        showWorkoutStep(plan, 0);
    }

    private void showWorkoutStep(List<Integer> plan, int position) {
        stopTimer();
        int exerciseIndex = plan.get(position);
        currentSeconds = exerciseSeconds[exerciseIndex];

        LinearLayout root = page(exerciseNames[exerciseIndex],
                (position + 1) + " / " + plan.size() + " • " + exerciseCategories[exerciseIndex]);
        root.setGravity(Gravity.CENTER_HORIZONTAL);

        LinearLayout display = card();
        display.setGravity(Gravity.CENTER_HORIZONTAL);
        TextView figure = title("◎", 96);
        figure.setTextColor(BLUE);
        figure.setGravity(Gravity.CENTER);
        display.addView(figure);
        TextView cue = title(exerciseCues[exerciseIndex], 18);
        cue.setGravity(Gravity.CENTER);
        display.addView(cue);
        TextView countdown = title(String.valueOf(currentSeconds), 58);
        countdown.setTextColor(BLUE);
        countdown.setGravity(Gravity.CENTER);
        display.addView(countdown);
        root.addView(display);

        speak("Sıradaki hareket " + exerciseNames[exerciseIndex] + ". " + exerciseCues[exerciseIndex]);

        Button play = actionButton("Başlat");
        play.setOnClickListener(v -> {
            if (timerRunning) {
                stopTimer();
                play.setText("Devam Et");
            } else {
                timerRunning = true;
                play.setText("Duraklat");
                timerRunnable = new Runnable() {
                    @Override
                    public void run() {
                        if (!timerRunning) return;
                        if (currentSeconds > 0) {
                            currentSeconds--;
                            countdown.setText(String.valueOf(currentSeconds));
                            if (currentSeconds == 5) speak("Son beş saniye");
                            handler.postDelayed(this, 1000);
                        } else {
                            timerRunning = false;
                            play.setText("Tamamlandı");
                            speak("Hareket tamamlandı");
                        }
                    }
                };
                handler.postDelayed(timerRunnable, 1000);
            }
        });
        root.addView(play);

        Button next = actionButton(position < plan.size() - 1 ? "Sonraki Hareket" : "Antrenmanı Bitir");
        next.setOnClickListener(v -> {
            if (position < plan.size() - 1) {
                showWorkoutStep(plan, position + 1);
            } else {
                int completed = prefs.getInt("completed", 0) + 1;
                prefs.edit().putInt("completed", completed).apply();
                speak("Antrenman tamamlandı. Tebrikler.");
                new AlertDialog.Builder(this)
                        .setTitle("Antrenman tamamlandı")
                        .setMessage("Bu antrenman rapor ekranına kaydedildi.")
                        .setPositiveButton("Ana Sayfa", (dialog, which) -> showHome())
                        .setCancelable(false)
                        .show();
            }
        });
        root.addView(next);

        Button close = actionButton("Antrenmandan Çık");
        close.setOnClickListener(v -> showHome());
        root.addView(close);
        setScrollableContent(root);
    }

    private void stopTimer() {
        timerRunning = false;
        if (timerRunnable != null) handler.removeCallbacks(timerRunnable);
    }

    @Override
    public void onBackPressed() {
        showHome();
    }

    @Override
    protected void onDestroy() {
        stopTimer();
        if (tts != null) {
            tts.stop();
            tts.shutdown();
        }
        super.onDestroy();
    }
}
