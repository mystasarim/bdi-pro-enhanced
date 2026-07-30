package com.mysdesign.formiva;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

final class ExerciseData {
    static final String[] NAMES = {
            "Yerinde Yürüyüş", "Sandalyeye Squat", "Eğik Şınav",
            "Sırt Çantasıyla Row", "Romanian Deadlift", "Glute Bridge",
            "Plank", "Split Squat", "Omuz Press", "Superman",
            "Side Plank", "Tempolu Yürüyüş", "Yavaş Kegel",
            "Hızlı Kegel", "Reverse Kegel", "Diyafram Nefesi"
    };

    static final String[] CATEGORIES = {
            "Isınma", "Bacak", "Üst Vücut", "Sırt", "Kalça", "Kalça",
            "Core", "Bacak", "Omuz", "Sırt", "Core", "Kondisyon",
            "Pelvik Taban", "Pelvik Taban", "Gevşeme", "Nefes"
    };

    static final String[] CUES = {
            "Dik dur, omuzlarını rahat bırak ve hafif tempoda yürü.",
            "Kalçanı geriye ver, göğsünü açık tut ve dizlerinin içe kaçmasına izin verme.",
            "Vücudunu düz tut, göğsünü kontrollü indir ve nefes vererek yukarı it.",
            "Sırtını düz tut, kürek kemiklerini yaklaştır ve dirseklerini geriye çek.",
            "Kalçanı geriye gönder, sırtını nötr tut ve kalçadan güç alarak doğrul.",
            "Topuklarından güç al, kalçanı yukarı kaldır ve üst noktada kısa süre sık.",
            "Başından topuğuna düz çizgi oluştur, karnını sık ve nefesini tutma.",
            "Dengeni koru, gövdeni dik tut ve kontrollü şekilde aşağı in.",
            "Karnını sık, ağırlığı omuz hizasından kontrollü biçimde yukarı it.",
            "Boynunu kasmadan kollarını ve üst gövdeni hafifçe kaldır.",
            "Dirseğini omzunun altında tut ve kalçanı yukarı kaldırarak düz çizgi oluştur.",
            "Rahat ama canlı bir tempoda yürü; konuşabilecek kadar kontrollü nefes al.",
            "Pelvik tabanı nazikçe sık, nefesini tutmadan kısa süre bekle ve tamamen gevşet.",
            "Kısa ve kontrollü şekilde sık-bırak; her tekrarda tam gevşemeye odaklan.",
            "Pelvik tabanı aşağı doğru serbest bırak, karın ve kalça çevresini gevşet.",
            "Burundan nefes alırken karnını doldur, ağızdan uzun ve sakin biçimde nefes ver."
    };

    static final int[] SECONDS = {
            45, 40, 35, 40, 40, 35, 30, 35, 35, 30, 25, 60, 30, 20, 30, 60
    };

    static final int[] DEFAULT_REPS = {
            0, 12, 10, 12, 12, 15, 0, 10, 10, 12, 0, 0, 8, 10, 5, 0
    };

    private ExerciseData() {
    }

    static List<Integer> personalPlan(boolean pelvicSupport) {
        Set<Integer> plan = new LinkedHashSet<>();
        int[] base = {0, 1, 2, 3, 4, 5, 6, 15};
        for (int index : base) plan.add(index);
        if (pelvicSupport) {
            plan.add(12);
            plan.add(13);
            plan.add(14);
            plan.add(15);
        }
        return new ArrayList<>(plan);
    }

    static int orderForCategory(String category) {
        if ("Isınma".equals(category)) return 1;
        if ("Bacak".equals(category) || "Üst Vücut".equals(category)
                || "Sırt".equals(category) || "Kalça".equals(category)
                || "Omuz".equals(category)) return 2;
        if ("Core".equals(category)) return 3;
        if ("Kondisyon".equals(category)) return 4;
        if ("Pelvik Taban".equals(category) || "Gevşeme".equals(category)) return 5;
        return 6;
    }
}