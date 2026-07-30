package com.mysdesign.formiva;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.RectF;
import android.graphics.Shader;
import android.view.View;
import android.view.animation.AccelerateDecelerateInterpolator;

class FormivaLogoView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);

    FormivaLogoView(Context context) {
        super(context);
        setLayerType(LAYER_TYPE_SOFTWARE, null);
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        float icon = Math.min(h * 0.78f, w * 0.25f);
        float left = 4f;
        float top = (h - icon) / 2f;

        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeJoin(Paint.Join.ROUND);
        paint.setStrokeWidth(icon * 0.16f);
        paint.setShader(new LinearGradient(left, top, left + icon, top + icon,
                new int[]{Color.rgb(78, 215, 168), Color.rgb(0, 194, 255), Color.rgb(17, 77, 216)},
                null, Shader.TileMode.CLAMP));

        Path f = new Path();
        f.moveTo(left + icon * 0.25f, top + icon * 0.82f);
        f.lineTo(left + icon * 0.25f, top + icon * 0.18f);
        f.lineTo(left + icon * 0.72f, top + icon * 0.18f);
        f.moveTo(left + icon * 0.25f, top + icon * 0.47f);
        f.lineTo(left + icon * 0.62f, top + icon * 0.47f);
        canvas.drawPath(f, paint);

        Path v = new Path();
        v.moveTo(left + icon * 0.49f, top + icon * 0.58f);
        v.lineTo(left + icon * 0.67f, top + icon * 0.84f);
        v.lineTo(left + icon * 0.88f, top + icon * 0.49f);
        canvas.drawPath(v, paint);
        paint.setShader(null);

        float textLeft = left + icon + icon * 0.10f;
        paint.setStyle(Paint.Style.FILL);
        paint.setTypeface(android.graphics.Typeface.create("sans-serif", android.graphics.Typeface.BOLD));
        paint.setTextSize(h * 0.45f);
        paint.setColor(Color.rgb(11, 29, 58));
        canvas.drawText("Formi", textLeft, h * 0.60f, paint);
        float prefix = paint.measureText("Formi");
        paint.setShader(new LinearGradient(textLeft + prefix, 0, textLeft + prefix + h, 0,
                Color.rgb(0, 194, 255), Color.rgb(78, 215, 168), Shader.TileMode.CLAMP));
        canvas.drawText("va", textLeft + prefix, h * 0.60f, paint);
        paint.setShader(null);
        paint.setTypeface(android.graphics.Typeface.create("sans-serif", android.graphics.Typeface.NORMAL));
        paint.setTextSize(h * 0.18f);
        paint.setColor(Color.rgb(102, 112, 133));
        canvas.drawText("Senin Dönüşümün. Senin Koçun.", textLeft, h * 0.86f, paint);
    }
}

class CoachAvatarView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private boolean male = true;
    private boolean selected;

    CoachAvatarView(Context context, boolean male) {
        super(context);
        this.male = male;
        setLayerType(LAYER_TYPE_SOFTWARE, null);
    }

    void setSelectedState(boolean selected) {
        this.selected = selected;
        invalidate();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        RectF box = new RectF(2, 2, w - 2, h - 2);
        Path clip = new Path();
        clip.addRoundRect(box, 28, 28, Path.Direction.CW);
        canvas.save();
        canvas.clipPath(clip);

        paint.setShader(new LinearGradient(0, 0, w, h,
                new int[]{Color.rgb(247, 250, 252), Color.rgb(232, 246, 242), Color.rgb(220, 240, 246)},
                null, Shader.TileMode.CLAMP));
        canvas.drawRect(box, paint);
        paint.setShader(null);

        float cx = w * 0.50f;
        float headY = h * 0.28f;
        float skin = male ? 0.0f : 1.0f;
        int skinColor = skin > 0.5f ? Color.rgb(228, 178, 142) : Color.rgb(218, 164, 128);

        paint.setColor(Color.rgb(27, 36, 55));
        if (male) {
            canvas.drawOval(new RectF(cx - w * 0.11f, headY - h * 0.11f,
                    cx + w * 0.11f, headY + h * 0.10f), paint);
        } else {
            canvas.drawOval(new RectF(cx - w * 0.15f, headY - h * 0.13f,
                    cx + w * 0.15f, headY + h * 0.17f), paint);
            canvas.drawCircle(cx + w * 0.12f, headY - h * 0.02f, w * 0.07f, paint);
        }

        paint.setColor(skinColor);
        canvas.drawOval(new RectF(cx - w * 0.085f, headY - h * 0.08f,
                cx + w * 0.085f, headY + h * 0.08f), paint);
        canvas.drawRoundRect(new RectF(cx - w * 0.035f, headY + h * 0.05f,
                cx + w * 0.035f, headY + h * 0.14f), 12, 12, paint);

        int clothing = male ? Color.rgb(11, 29, 58) : Color.rgb(20, 138, 121);
        paint.setColor(clothing);
        Path torso = new Path();
        if (male) {
            torso.moveTo(cx - w * 0.18f, h * 0.43f);
            torso.quadTo(cx, h * 0.36f, cx + w * 0.18f, h * 0.43f);
            torso.lineTo(cx + w * 0.13f, h * 0.82f);
            torso.lineTo(cx - w * 0.13f, h * 0.82f);
        } else {
            torso.moveTo(cx - w * 0.14f, h * 0.43f);
            torso.quadTo(cx, h * 0.38f, cx + w * 0.14f, h * 0.43f);
            torso.lineTo(cx + w * 0.11f, h * 0.82f);
            torso.lineTo(cx - w * 0.11f, h * 0.82f);
        }
        torso.close();
        canvas.drawPath(torso, paint);

        paint.setColor(skinColor);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeWidth(w * 0.075f);
        canvas.drawLine(cx - w * 0.14f, h * 0.48f, cx + w * 0.13f, h * 0.66f, paint);
        canvas.drawLine(cx + w * 0.14f, h * 0.48f, cx - w * 0.13f, h * 0.66f, paint);
        paint.setStyle(Paint.Style.FILL);

        if (selected) {
            paint.setColor(Color.rgb(78, 215, 168));
            canvas.drawCircle(w * 0.86f, h * 0.15f, w * 0.07f, paint);
            paint.setColor(Color.WHITE);
            paint.setStyle(Paint.Style.STROKE);
            paint.setStrokeWidth(w * 0.018f);
            Path check = new Path();
            check.moveTo(w * 0.825f, h * 0.15f);
            check.lineTo(w * 0.85f, h * 0.175f);
            check.lineTo(w * 0.90f, h * 0.115f);
            canvas.drawPath(check, paint);
            paint.setStyle(Paint.Style.FILL);
        }
        canvas.restore();

        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(selected ? 5 : 2);
        paint.setColor(selected ? Color.rgb(78, 215, 168) : Color.rgb(222, 228, 235));
        canvas.drawRoundRect(box, 28, 28, paint);
        paint.setStyle(Paint.Style.FILL);
    }
}

class ExerciseVisualView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final ValueAnimator animator;
    private int exerciseIndex;
    private boolean male = true;
    private float phase;

    ExerciseVisualView(Context context) {
        super(context);
        setLayerType(LAYER_TYPE_SOFTWARE, null);
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(1500);
        animator.setRepeatMode(ValueAnimator.REVERSE);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.setInterpolator(new AccelerateDecelerateInterpolator());
        animator.addUpdateListener(animation -> {
            phase = (float) animation.getAnimatedValue();
            invalidate();
        });
    }

    void setExercise(int exerciseIndex, boolean male) {
        this.exerciseIndex = exerciseIndex;
        this.male = male;
        invalidate();
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        animator.start();
    }

    @Override
    protected void onDetachedFromWindow() {
        animator.cancel();
        super.onDetachedFromWindow();
    }

    private float l(float a, float b) {
        return a + (b - a) * phase;
    }

    private void limb(Canvas c, float x1, float y1, float x2, float y2, int color, float width) {
        paint.setColor(color);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeWidth(width);
        c.drawLine(x1, y1, x2, y2, paint);
        paint.setStyle(Paint.Style.FILL);
    }

    private void mannequin(Canvas c, float headX, float headY, float shoulderX, float shoulderY,
                           float hipX, float hipY,
                           float elbowLX, float elbowLY, float handLX, float handLY,
                           float elbowRX, float elbowRY, float handRX, float handRY,
                           float kneeLX, float kneeLY, float ankleLX, float ankleLY,
                           float kneeRX, float kneeRY, float ankleRX, float ankleRY) {
        int skin = male ? Color.rgb(217, 165, 128) : Color.rgb(229, 181, 145);
        int outfit = male ? Color.rgb(11, 29, 58) : Color.rgb(20, 138, 121);
        float unit = Math.min(getWidth(), getHeight());
        float limbWidth = unit * 0.034f;

        limb(c, shoulderX, shoulderY, elbowLX, elbowLY, skin, limbWidth);
        limb(c, elbowLX, elbowLY, handLX, handLY, skin, limbWidth * 0.82f);
        limb(c, shoulderX, shoulderY, elbowRX, elbowRY, skin, limbWidth);
        limb(c, elbowRX, elbowRY, handRX, handRY, skin, limbWidth * 0.82f);
        limb(c, hipX, hipY, kneeLX, kneeLY, outfit, limbWidth * 1.15f);
        limb(c, kneeLX, kneeLY, ankleLX, ankleLY, skin, limbWidth);
        limb(c, hipX, hipY, kneeRX, kneeRY, outfit, limbWidth * 1.15f);
        limb(c, kneeRX, kneeRY, ankleRX, ankleRY, skin, limbWidth);

        paint.setColor(outfit);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeWidth(male ? unit * 0.11f : unit * 0.085f);
        c.drawLine(shoulderX, shoulderY, hipX, hipY, paint);
        paint.setStyle(Paint.Style.FILL);

        paint.setColor(skin);
        c.drawCircle(headX, headY, unit * 0.055f, paint);
        paint.setColor(Color.rgb(30, 38, 54));
        if (male) {
            c.drawArc(new RectF(headX - unit * 0.055f, headY - unit * 0.06f,
                    headX + unit * 0.055f, headY + unit * 0.04f), 190, 160, true, paint);
        } else {
            c.drawArc(new RectF(headX - unit * 0.068f, headY - unit * 0.07f,
                    headX + unit * 0.068f, headY + unit * 0.065f), 175, 190, true, paint);
            c.drawCircle(headX + unit * 0.055f, headY, unit * 0.026f, paint);
        }

        paint.setColor(Color.rgb(11, 29, 58));
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(unit * 0.018f);
        c.drawLine(ankleLX - unit * 0.02f, ankleLY, ankleLX + unit * 0.045f, ankleLY, paint);
        c.drawLine(ankleRX - unit * 0.02f, ankleRY, ankleRX + unit * 0.045f, ankleRY, paint);
        paint.setStyle(Paint.Style.FILL);
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        RectF area = new RectF(3, 3, w - 3, h - 3);
        Path clip = new Path();
        clip.addRoundRect(area, 34, 34, Path.Direction.CW);
        canvas.save();
        canvas.clipPath(clip);
        paint.setShader(new LinearGradient(0, 0, w, h,
                new int[]{Color.WHITE, Color.rgb(241, 249, 247), Color.rgb(230, 243, 248)},
                null, Shader.TileMode.CLAMP));
        canvas.drawRect(area, paint);
        paint.setShader(null);
        paint.setColor(Color.rgb(216, 229, 232));
        canvas.drawRoundRect(new RectF(w * 0.12f, h * 0.82f, w * 0.88f, h * 0.85f), 20, 20, paint);

        switch (exerciseIndex) {
            case 1: drawSquat(canvas, w, h); break;
            case 2: case 6: drawPushUp(canvas, w, h, exerciseIndex == 6); break;
            case 3: drawRow(canvas, w, h); break;
            case 4: drawDeadlift(canvas, w, h); break;
            case 5: drawBridge(canvas, w, h); break;
            case 7: drawLunge(canvas, w, h); break;
            case 8: drawPress(canvas, w, h); break;
            case 9: drawSuperman(canvas, w, h); break;
            case 10: drawSidePlank(canvas, w, h); break;
            case 12: case 13: case 14: case 15: drawBreathing(canvas, w, h); break;
            default: drawWalk(canvas, w, h); break;
        }

        canvas.restore();
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(2);
        paint.setColor(Color.rgb(226, 232, 240));
        canvas.drawRoundRect(area, 34, 34, paint);
        paint.setStyle(Paint.Style.FILL);
    }

    private void drawWalk(Canvas c, float w, float h) {
        float cx = w * 0.50f;
        float step = (phase - 0.5f) * w * 0.18f;
        mannequin(c, cx, h * 0.22f, cx, h * 0.35f, cx, h * 0.57f,
                cx - step, h * 0.45f, cx - step * 1.4f, h * 0.58f,
                cx + step, h * 0.45f, cx + step * 1.4f, h * 0.58f,
                cx - step, h * 0.70f, cx - step * 1.7f, h * 0.82f,
                cx + step, h * 0.70f, cx + step * 1.7f, h * 0.82f);
    }

    private void drawSquat(Canvas c, float w, float h) {
        float drop = l(0, h * 0.18f);
        float cx = w * 0.50f;
        mannequin(c, cx, h * 0.20f + drop, cx, h * 0.34f + drop, cx, h * 0.56f + drop,
                w * 0.39f, h * 0.43f + drop, w * 0.55f, h * 0.47f + drop,
                w * 0.61f, h * 0.43f + drop, w * 0.45f, h * 0.47f + drop,
                l(w * 0.45f, w * 0.34f), l(h * 0.70f, h * 0.73f), w * 0.30f, h * 0.83f,
                l(w * 0.55f, w * 0.66f), l(h * 0.70f, h * 0.73f), w * 0.70f, h * 0.83f);
    }

    private void drawPushUp(Canvas c, float w, float h, boolean plank) {
        float bend = plank ? 0 : l(0, h * 0.11f);
        mannequin(c, w * 0.73f, h * 0.47f + bend * 0.35f,
                w * 0.61f, h * 0.52f + bend * 0.45f,
                w * 0.39f, h * 0.58f + bend * 0.2f,
                w * 0.62f, h * 0.65f, w * 0.69f, h * 0.80f,
                w * 0.55f, h * 0.65f, w * 0.50f, h * 0.80f,
                w * 0.29f, h * 0.66f, w * 0.20f, h * 0.80f,
                w * 0.31f, h * 0.66f, w * 0.22f, h * 0.80f);
    }

    private void drawRow(Canvas c, float w, float h) {
        float pull = l(0, w * 0.12f);
        mannequin(c, w * 0.59f, h * 0.30f, w * 0.53f, h * 0.42f, w * 0.44f, h * 0.58f,
                w * 0.43f, h * 0.50f, w * 0.30f + pull, h * 0.58f,
                w * 0.61f, h * 0.50f, w * 0.73f - pull, h * 0.58f,
                w * 0.38f, h * 0.70f, w * 0.31f, h * 0.82f,
                w * 0.55f, h * 0.70f, w * 0.61f, h * 0.82f);
        paint.setColor(Color.rgb(11, 29, 58));
        c.drawCircle(w * 0.30f + pull, h * 0.58f, w * 0.025f, paint);
        c.drawCircle(w * 0.73f - pull, h * 0.58f, w * 0.025f, paint);
    }

    private void drawDeadlift(Canvas c, float w, float h) {
        float hinge = l(0, h * 0.20f);
        mannequin(c, w * 0.50f + hinge * 0.35f, h * 0.20f + hinge,
                w * 0.50f + hinge * 0.20f, h * 0.34f + hinge * 0.75f,
                w * 0.50f, h * 0.56f,
                w * 0.43f, h * 0.46f + hinge, w * 0.43f, h * 0.63f + hinge * 0.45f,
                w * 0.57f, h * 0.46f + hinge, w * 0.57f, h * 0.63f + hinge * 0.45f,
                w * 0.42f, h * 0.70f, w * 0.38f, h * 0.83f,
                w * 0.58f, h * 0.70f, w * 0.62f, h * 0.83f);
    }

    private void drawBridge(Canvas c, float w, float h) {
        float lift = l(0, h * 0.15f);
        mannequin(c, w * 0.75f, h * 0.66f, w * 0.65f, h * 0.66f - lift * 0.5f,
                w * 0.45f, h * 0.69f - lift,
                w * 0.66f, h * 0.74f, w * 0.57f, h * 0.79f,
                w * 0.69f, h * 0.74f, w * 0.77f, h * 0.79f,
                w * 0.34f, h * 0.68f, w * 0.28f, h * 0.82f,
                w * 0.38f, h * 0.68f, w * 0.44f, h * 0.82f);
    }

    private void drawLunge(Canvas c, float w, float h) {
        float drop = l(0, h * 0.15f);
        mannequin(c, w * 0.50f, h * 0.20f + drop, w * 0.50f, h * 0.34f + drop,
                w * 0.50f, h * 0.56f + drop,
                w * 0.40f, h * 0.45f + drop, w * 0.50f, h * 0.52f + drop,
                w * 0.60f, h * 0.45f + drop, w * 0.50f, h * 0.52f + drop,
                w * 0.37f, h * 0.69f + drop * 0.35f, w * 0.25f, h * 0.82f,
                w * 0.63f, h * 0.69f + drop * 0.35f, w * 0.77f, h * 0.82f);
    }

    private void drawPress(Canvas c, float w, float h) {
        float up = l(0, h * 0.22f);
        mannequin(c, w * 0.50f, h * 0.21f, w * 0.50f, h * 0.35f, w * 0.50f, h * 0.58f,
                w * 0.39f, h * 0.42f - up * 0.5f, w * 0.38f, h * 0.36f - up,
                w * 0.61f, h * 0.42f - up * 0.5f, w * 0.62f, h * 0.36f - up,
                w * 0.43f, h * 0.71f, w * 0.40f, h * 0.83f,
                w * 0.57f, h * 0.71f, w * 0.60f, h * 0.83f);
        paint.setColor(Color.rgb(11, 29, 58));
        c.drawCircle(w * 0.38f, h * 0.36f - up, w * 0.026f, paint);
        c.drawCircle(w * 0.62f, h * 0.36f - up, w * 0.026f, paint);
    }

    private void drawSuperman(Canvas c, float w, float h) {
        float lift = l(0, h * 0.10f);
        mannequin(c, w * 0.73f, h * 0.67f - lift, w * 0.62f, h * 0.70f - lift,
                w * 0.47f, h * 0.72f - lift * 0.6f,
                w * 0.77f, h * 0.62f - lift, w * 0.88f, h * 0.55f - lift,
                w * 0.70f, h * 0.62f - lift, w * 0.82f, h * 0.52f - lift,
                w * 0.36f, h * 0.74f - lift, w * 0.22f, h * 0.68f - lift,
                w * 0.38f, h * 0.76f - lift, w * 0.24f, h * 0.73f - lift);
    }

    private void drawSidePlank(Canvas c, float w, float h) {
        float lift = l(0, h * 0.08f);
        mannequin(c, w * 0.73f, h * 0.48f - lift, w * 0.62f, h * 0.52f - lift,
                w * 0.42f, h * 0.61f - lift,
                w * 0.62f, h * 0.68f, w * 0.58f, h * 0.80f,
                w * 0.58f, h * 0.40f - lift, w * 0.54f, h * 0.26f - lift,
                w * 0.31f, h * 0.68f, w * 0.20f, h * 0.80f,
                w * 0.33f, h * 0.65f, w * 0.22f, h * 0.80f);
    }

    private void drawBreathing(Canvas c, float w, float h) {
        mannequin(c, w * 0.50f, h * 0.22f, w * 0.50f, h * 0.36f, w * 0.50f, h * 0.59f,
                w * 0.39f, h * 0.48f, w * 0.45f, h * 0.58f,
                w * 0.61f, h * 0.48f, w * 0.55f, h * 0.58f,
                w * 0.41f, h * 0.72f, w * 0.33f, h * 0.82f,
                w * 0.59f, h * 0.72f, w * 0.67f, h * 0.82f);
        float radius = l(w * 0.035f, w * 0.085f);
        paint.setColor(Color.argb(80, 78, 215, 168));
        c.drawCircle(w * 0.50f, h * 0.56f, radius, paint);
        paint.setColor(Color.rgb(78, 215, 168));
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(5);
        c.drawCircle(w * 0.50f, h * 0.56f, radius, paint);
        paint.setStyle(Paint.Style.FILL);
    }
}

class ProgressRingView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private String primary = "0";
    private String secondary = "";
    private float progress;

    ProgressRingView(Context context) {
        super(context);
    }

    void setValues(String primary, String secondary, float progress) {
        this.primary = primary;
        this.secondary = secondary;
        this.progress = Math.max(0f, Math.min(1f, progress));
        invalidate();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float size = Math.min(getWidth(), getHeight());
        float stroke = size * 0.10f;
        RectF oval = new RectF(stroke, stroke, getWidth() - stroke, getHeight() - stroke);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeWidth(stroke);
        paint.setColor(Color.rgb(231, 237, 242));
        canvas.drawArc(oval, -90, 360, false, paint);
        paint.setShader(new LinearGradient(0, 0, getWidth(), getHeight(),
                Color.rgb(78, 215, 168), Color.rgb(0, 194, 255), Shader.TileMode.CLAMP));
        canvas.drawArc(oval, -90, 360 * progress, false, paint);
        paint.setShader(null);
        paint.setStyle(Paint.Style.FILL);
        paint.setTextAlign(Paint.Align.CENTER);
        paint.setTypeface(android.graphics.Typeface.create("sans-serif", android.graphics.Typeface.BOLD));
        paint.setTextSize(size * 0.25f);
        paint.setColor(Color.rgb(11, 29, 58));
        canvas.drawText(primary, getWidth() / 2f, getHeight() * 0.52f, paint);
        paint.setTypeface(android.graphics.Typeface.create("sans-serif", android.graphics.Typeface.NORMAL));
        paint.setTextSize(size * 0.10f);
        paint.setColor(Color.rgb(102, 112, 133));
        canvas.drawText(secondary, getWidth() / 2f, getHeight() * 0.68f, paint);
    }
}

class MiniLineChartView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private float[] values = {0.9f, 0.78f, 0.81f, 0.62f, 0.52f, 0.38f, 0.30f};

    MiniLineChartView(Context context) {
        super(context);
    }

    void setValues(float[] values) {
        if (values != null && values.length > 1) this.values = values;
        invalidate();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        paint.setColor(Color.rgb(232, 237, 242));
        paint.setStrokeWidth(2);
        for (int i = 1; i <= 3; i++) {
            float y = h * i / 4f;
            canvas.drawLine(0, y, w, y, paint);
        }
        Path path = new Path();
        for (int i = 0; i < values.length; i++) {
            float x = w * i / (values.length - 1f);
            float y = h * (0.12f + 0.75f * values[i]);
            if (i == 0) path.moveTo(x, y); else path.lineTo(x, y);
        }
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeJoin(Paint.Join.ROUND);
        paint.setStrokeWidth(6);
        paint.setShader(new LinearGradient(0, 0, w, 0,
                Color.rgb(78, 215, 168), Color.rgb(0, 194, 255), Shader.TileMode.CLAMP));
        canvas.drawPath(path, paint);
        paint.setShader(null);
        paint.setStyle(Paint.Style.FILL);
        paint.setColor(Color.rgb(17, 77, 216));
        for (int i = 0; i < values.length; i++) {
            float x = w * i / (values.length - 1f);
            float y = h * (0.12f + 0.75f * values[i]);
            canvas.drawCircle(x, y, 5, paint);
        }
    }
}

class WaveformView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final ValueAnimator animator;
    private float phase;

    WaveformView(Context context) {
        super(context);
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(900);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.addUpdateListener(a -> {
            phase = (float) a.getAnimatedValue();
            invalidate();
        });
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        animator.start();
    }

    @Override
    protected void onDetachedFromWindow() {
        animator.cancel();
        super.onDetachedFromWindow();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        int bars = 22;
        float gap = getWidth() / (float) bars;
        paint.setStrokeWidth(Math.max(3, gap * 0.32f));
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setShader(new LinearGradient(0, 0, getWidth(), 0,
                Color.rgb(78, 215, 168), Color.rgb(0, 194, 255), Shader.TileMode.CLAMP));
        for (int i = 0; i < bars; i++) {
            float wave = (float) (0.20 + 0.70 * Math.abs(Math.sin(i * 0.72 + phase * Math.PI * 2)));
            float barH = getHeight() * wave;
            float x = gap * i + gap / 2f;
            canvas.drawLine(x, (getHeight() - barH) / 2f, x, (getHeight() + barH) / 2f, paint);
        }
        paint.setShader(null);
    }
}